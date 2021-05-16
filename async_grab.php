<?php

error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('memory_limit', '2048M');

require 'vendor/autoload.php';

use Amp\Loop;
use Amp\Producer;
use app\Log;
use Monolog\ErrorHandler;
use Amp\Sync\LocalSemaphore;
use function Amp\Sync\ConcurrentIterator\each;


$from = new DateTime('2006-02-01');
$to = new DateTime('2006-12-31');


ErrorHandler::register(Log::getLogger());
Loop::setErrorHandler([Log::class, 'critical']);


Loop::run(function () use ($from, $to) {
    $iterator = new Producer(function ($emit) use ($from, $to) {
        try {
            while ($from < $to) {

            }
//            for ($i = 0; false !== $line = fgets($file); $i++) {
//                yield $emit(trim($line));
//            }
        } finally {
            Log::debug("END OF FILE AT LINE {$i}");
        }
    });

    $iterator = filter($iterator, function ($line) {
        return isValidName($line);
    });
    $parser = new Parser();

    yield each($iterator, new LocalSemaphore(30), $parser);
});