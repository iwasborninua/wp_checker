<?php
require 'vendor/autoload.php';
require 'lib/functions.php';

use Amp\Delayed;
use Amp\Dns;
use Amp\Dns\DnsException;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Loop;
use Amp\Promise;
use Amp\Producer;
use Amp\Sync\LocalSemaphore;
use Amp\Http\Client\Request;
use Symfony\Component\DomCrawler\Crawler;
use function Amp\Sync\ConcurrentIterator\each;

$verified = fopen('data/verified.txt', 'w+');
$bad = fopen('data/bad.txt', 'w+');

// Устанавливаем конфиг гугловского DNS
Dns\resolver(new Dns\Rfc1035StubResolver(null, new class implements Dns\ConfigLoader {
    public function loadConfig(): Promise
    {
        return Amp\call(function () {
            $hosts = yield (new Dns\HostLoader)->loadHosts();

            return new Dns\Config([
                "8.8.8.8:53",
                "[2001:4860:4860::8888]:53",
            ], $hosts, $timeout = 5000, $attempts = 3);
        });
    }
}));

Loop::setErrorHandler(function (\Throwable $e) {
    echo "error handler -> " . $e->getMessage() . PHP_EOL;
});

try {
    Loop::run(function () use ($verified, $bad) {
        $iterator = new Producer(function ($emit) {
            $file = fopen('data/domains.txt', 'r');
            while (false !== $line = fgets($file)) {
                    yield $emit(trim($line));
            }
        });

        $client = HttpClientBuilder::buildDefault();
        $client_disable_redirect = (new HttpClientBuilder())
            ->followRedirects(0)
            ->build();

        yield each($iterator, new LocalSemaphore(50), function ($line) use ($client, $client_disable_redirect, $verified, $bad) {
            try {
                $request = new Request( DP . $line . WP_ADMIN_PATH);
                $request->setTcpConnectTimeout(2400);

                $response = yield $client->request($request);
                if ($response->getStatus() == 200) {
                    $login_form = (new Crawler((string) yield $response->getBody()->buffer()))
                        ->filter('form#loginform')->count();
                    if ($login_form > 0) {
                        $author_request = new Request(DP . $line . AUTHOR_DORK);
                        $author_request->setTcpConnectTimeout(2400);
                        $response = yield $client_disable_redirect->request($author_request);

                        if ($response->getStatus() == "301") {
                            $location = $response->getHeaders()['location'][0];
                            $location_array = array_diff(explode('/', $location), LOCATION_URL_RULES);
                            $login = end($location_array);

                            if (count($location_array) >= 2 && $line != end($location_array)) {
                                echo "verifed|login: {$line}" . PHP_EOL;
                                $url = DP . $line . "/wp-login.php;" . $login;
                            } else {
                                echo "verifed: {$line}" . PHP_EOL;
                                $url = DP . $line . WP_ADMIN_PATH;
                            }

                            fwrite($verified, $url . PHP_EOL);
                        } else {
                            echo "verifed: {$line}" . PHP_EOL;
                            fwrite($verified, DP . $line . WP_ADMIN_PATH . PHP_EOL);
                        }


                    } else {
                        echo "form not found: {$line}" . PHP_EOL;
                        fwrite($bad,$line. PHP_EOL);
                    }
                } else {
                    fwrite($bad, $line . PHP_EOL);
                }
            } catch (Exception $e) {
                echo "Bad request: " . $line . PHP_EOL;
            }
        });
    });
} catch (Throwable $loopException) {
    echo "loop bubbled exception caught -> " . $loopException->getMessage() . PHP_EOL;
}

echo "Скрипт отработал корректно.";
