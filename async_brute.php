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

$logins    = array_map('trimRow', file('data/logins.txt'));
$passwords = array_map('trimRow', file('data/passwords.txt'));
$verified  = fopen('data/verified.txt', 'r+') or die('verified.txt not exist!');

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
    Loop::run(function () use ($logins, $passwords, $verified) {
        $iterator = new Producer(function ($emit) use ($verified, $logins, $passwords) {
            foreach ($logins as $login) {
                foreach ($passwords as $password) {
                    while (null !== $line = fgets($verified)) {
                        yield $emit([$line, $login, $password]);
                    }
                    rewind($verified);
                }
            }
        });

        yield each($emit, new LocalSemaphore(5), function ($line) {
            echo $line . PHP_EOL;
        });


    });
} catch (Throwable $loopException) {
    echo "loop bubbled exception caught -> " . $loopException->getMessage() . PHP_EOL;
}