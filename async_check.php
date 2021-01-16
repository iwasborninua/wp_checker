<?php
error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('memory_limit', '2048M');

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
use Monolog\ErrorHandler;
use function Amp\Iterator\filter;
use function Amp\Dns\resolver;
use function Amp\Dns\isValidName;
use function Amp\Sync\ConcurrentIterator\each;
use app\dns\Resolver;
use app\Log;
use app\Parser;

ErrorHandler::register(Log::getLogger());
Loop::setErrorHandler([Log::class, 'critical']);
resolver(new Resolver());

Dns\resolver(new class implements Dns\Resolver {
    protected $resolvers = [];
    public function __construct() {
        $this->resolvers[] = new Dns\Rfc1035StubResolver(null, new CustomConfigLoader(['1.1.1.1:53', '1.0.0.1:53']));
        $this->resolvers[] = new Dns\Rfc1035StubResolver(null, new CustomConfigLoader(['8.8.8.8:53', '8.8.4.4:53']));
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

        $client = (new HttpClientBuilder())->retry(10)->build();
        $client_disable_redirect = (new HttpClientBuilder())
            ->retry(10)
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