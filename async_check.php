<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');


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
$dns_fail = fopen('data/dns_fail.txt', 'w+');

// Устанавливаем конфиг гугловского DNS
//$dnsCache = new Amp\Cache\FileCache('data/dns/', new Amp\Sync\LocalKeyedMutex());

class CustomConfigLoader implements Dns\ConfigLoader
{
    protected $dns;
    public function __construct(array $dns)
    {
        $this->dns = $dns;
    }

    public function loadConfig(): Promise
    {
        return Amp\call(function () {
            $hosts = yield (new Dns\HostLoader)->loadHosts();

            return new Dns\Config($this->dns, $hosts, $timeout = 10000, $attempts = 10);
        });
    }
}

Dns\resolver(new class implements Dns\Resolver {
    protected $resolvers = [];
    public function __construct() {
        $this->resolvers[] = new Dns\Rfc1035StubResolver(null, new CustomConfigLoader(['1.1.1.1:53', '1.0.0.1:53']));
        $this->resolvers[] = new Dns\Rfc1035StubResolver(null, new CustomConfigLoader(['8.8.8.8:53', '8.8.4.4:53']));
        $this->resolvers[] = new Dns\Rfc1035StubResolver(null, new CustomConfigLoader(['208.67.222.222:53', '208.67.220.220:53']));
        $this->resolvers[] = new Dns\Rfc1035StubResolver(null, new CustomConfigLoader([
            '209.244.0.3:53',
            '209.244.0.4:53',
            '4.2.2.1:53',
            '4.2.2.2:53',
            '4.2.2.3:53',
            '4.2.2.4:53'
        ]));
        $this->resolvers[] = new Dns\Rfc1035StubResolver(null, new CustomConfigLoader([
            '8.26.56.26:53',
            '8.20.247.20:53'
        ]));
    }
    public function getResolver() : Dns\Resolver {
        return $this->resolvers[array_rand($this->resolvers)];
    }
    public function resolve(string $name, int $typeRestriction = null) : \Amp\Promise {
        return $this->getResolver()->resolve($name, $typeRestriction);
    }
    public function query(string $name, int $type) : \Amp\Promise {
        return $this->getResolver()->resolve($name, $typeRestriction);
    }
});

Loop::setErrorHandler(function (\Throwable $e) {
    echo "error handler -> " . $e->getMessage() . PHP_EOL;
    Loop::stop();
    exit($e->getMessage() . PHP_EOL);
});

try {
    Loop::run(function () use ($verified, $bad, $dns_fail) {
        $iterator = new Producer(function ($emit) {
            $file = fopen('data/domains.txt', 'r');
            $i = 0;
            try {
                while (false !== $line = fgets($file)) {
                    yield $emit(trim($line));
                }
            } finally {
                var_dump("END OF FILE AT LINE {$i}");
            }
        });

        $client = (new HttpClientBuilder())->retry(3)->build();
        $client_disable_redirect = (new HttpClientBuilder())
            ->retry(3)
            ->followRedirects(0)
            ->build();

        yield each($iterator, new LocalSemaphore(50), function ($line) use ($client, $client_disable_redirect, $verified, $bad, $dns_fail) {
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
                                echo round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB' . PHP_EOL;
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
            } catch (DnsException $e) {
                fwrite($dns_fail, $line . " :" . $dns_fail);
            } catch (\Throwable $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        });
    });
} catch (Throwable $loopException) {
    echo "loop bubbled exception caught -> " . $loopException->getMessage() . PHP_EOL;
}

echo "Скрипт отработал корректно.";