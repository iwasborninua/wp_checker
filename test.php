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
use function Amp\Sync\ConcurrentIterator\each;

$verified = fopen('data/verified.txt', 'w+');
$bad = fopen('data/bad.txt', 'w+');

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

Loop::run(function () use ($verified, $bad) {
    $iterator = new Producer(function ($emit) {
        $file = fopen('data/domains.txt', 'r');
        while (false !== $line = fgets($file)) {
            yield $emit(trim($line));
        }
    });

    $client = HttpClientBuilder::buildDefault();

    yield each($iterator, new LocalSemaphore(50), function ($line) use ($client, $verified, $bad) {
        $request = new Request( DP . $line . WP_ADMIN_PATH);
        $request->setTcpConnectTimeout(2400);
        try {
            $response = yield $client->request($request);
            if ($response->getStatus() == 200) {
                fwrite($verified, DP . $line . WP_ADMIN_PATH . PHP_EOL);
            } else {
                fwrite($bad, $line . PHP_EOL);
            }
        } catch (Exception $e) {
            echo $e->getCode() . PHP_EOL;
        }
    });
});