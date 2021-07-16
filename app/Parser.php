<?php

namespace app;

use Exception;
use Generator;
use Throwable;
use Amp\Dns\DnsException;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Symfony\Component\DomCrawler\Crawler;
use function Amp\asyncCall;
use function GuzzleHttp\default_user_agent;

class Parser
{
    protected const DEFAULT_SCHEME = 'http://';
    protected const SECURE_SCHEME = 'https://';
    protected const WP_ADMIN_PATH  = '/wp-login.php';
    protected const WP_AUTHOR_PATH  = '/?author=1';
    protected const VALID_STATUSES = [200];
    protected const REDIRECT_STATUSES = [301, 302, 307, 308];
    protected const FORM_CSS_SELECTOR = 'form#loginform';

    protected $client;
    protected $dir;

    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->build();

        $dataDir = dirname(__DIR__) . '/data/';
        if (!is_dir($dataDir) || !is_writable($dataDir)) {
            throw new Exception($dataDir . ' is not directory or not writeable');
        }

        $this->dir = $dataDir . date('YmdHis');
        mkdir($this->dir, 0777);
    }

    public function __destruct()
    {
        if (!glob($this->dir . "/*")) {
            rmdir($this->dir);
        }
    }

    protected function write(string $flag, string $data) : int
    {
        return file_put_contents("{$this->dir}/{$flag}.txt", "{$data}\r\n", FILE_APPEND);
    }

    public function __invoke(string $domain) : Generator
    {
        try {
            yield from $this->handle($domain);
        } catch (DnsException $e) {
            $this->write('dns-fail', $domain);
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

    protected function handle(string $domain) : Generator
    {
        $selected_schema = static::DEFAULT_SCHEME;

        // first query
        $uri = $selected_schema . $domain;
        $request = new Request($uri);
        $request->setTcpConnectTimeout(2400);
        $response = yield $this->client->request($request);

        if (in_array($response->getStatus(),self::REDIRECT_STATUSES) && $response->getHeader("Location") != null) {
            $uri = $response->getHeader("Location");
            $this->write('redirect', $uri);
            return;
        }

        // if we have valid response...
        if (in_array($response->getStatus(),self::VALID_STATUSES)) {
            // Check redirect's to https::// protocol or www subdomain
            if ($response->getPreviousResponse()) {
                $location = $response->getPreviousResponse()->getHeader("Location");

                if (null != $location) {
                    $selected_schema = str_contains($location, self::SECURE_SCHEME) ? self::SECURE_SCHEME : self::DEFAULT_SCHEME;
                    $domain = str_contains($location, 'www.') ? 'www.' . $domain : $domain;
                    $uri = $selected_schema . $domain . self::WP_ADMIN_PATH;
                } else {
                    return;
                }
            } else {
                $uri = $selected_schema . $domain . self::WP_ADMIN_PATH;
            }

            // second query
            $request = new Request($uri);
            $request->setTcpConnectTimeout(2400);
            $response = yield $this->client->request($request);

            if (in_array($response->getStatus(), static::VALID_STATUSES)) {

                if (null === $parsed = yield from $this->parse($domain, $response)) {
                    Log::info("form not found: {$domain}");
                    $this->write('bad', $domain);
                    return;
                }

                [, $author] = $parsed;
                if (null !== $author) {
                    Log::notice("verifed|login: {$domain}");
                    $this->write('verified', "{$uri};{$author}");
                } else {
                    Log::notice("verifed: {$domain}");
                    $this->write('verified', $uri);
                }
            } else {
                $this->write('bad', $domain);
            }

        }

    }

    protected function  parse(string $domain, Response $response) : Generator
    {
        $body = yield $response->getBody()->buffer();
        $loginForm = (new Crawler($body))->filter(static::FORM_CSS_SELECTOR);

        if ($loginForm->count() > 0) {

            $uri = static::DEFAULT_SCHEME . $domain . static::WP_AUTHOR_PATH;
            $request = new Request($uri);
            $request->setTcpConnectTimeout(2400);

            $response = yield $this->client->request($request);
            $originalResponse = $response->getOriginalResponse();
            if ($originalResponse->getStatus() === 301 && $originalResponse->hasHeader('Location')) {

                $location = $originalResponse->getHeader('Location');
                $path = parse_url($location, PHP_URL_PATH);

                if (preg_match('#^/author/(?<author>.[^/]*)#i', $path, $matches)) {
                    return [$domain, $matches['author']];
                }
            }

            // let consume body to prevent memory leaks
            asyncCall([$response->getBody(), 'buffer']);

            return [$domain, null];

        }

        return null;
    }

    protected function selectScheme($previosResponse, $domain) {
        if (null != $previosResponse && null !== strpos($previosResponse->getHeader("Location"), self::SECURE_SCHEME)) {
            return static::SECURE_SCHEME . $domain . static::WP_ADMIN_PATH;
        } else {
            return static::DEFAULT_SCHEME . $domain . static::WP_ADMIN_PATH;
        }
    }
}
