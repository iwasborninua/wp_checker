<?php

error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('memory_limit', '2048M');

require 'vendor/autoload.php';

use Amp\Loop;
use Amp\Producer;
use app\Grabber;
use app\Log;
use Monolog\ErrorHandler;
use Amp\Sync\LocalSemaphore;
use function Amp\Sync\ConcurrentIterator\each;


$from = new DateTime('2014-05-01');
$to = new DateTime('2014-05-11');


ErrorHandler::register(Log::getLogger());
Loop::setErrorHandler([Log::class, 'critical']);


Loop::run(function () use ($from, $to) {
    $iterator = new Producer(function ($emit) use ($from, $to) {
        try {
            while ($from < $to) {
                yield $emit('http://whoistory.com/' . $from->format("Y/m/d"));
                $from->modify('+ 1 day');
            }
        } finally {
            echo "Что то пошло не так, хуй знает что" . PHP_EOL;
        }
    });

    $grabber = new Grabber();

    yield each($iterator, new LocalSemaphore(30), $grabber);
});

Log::debug('DONE');