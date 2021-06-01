<?php

error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('memory_limit', '2048M');

require 'vendor/autoload.php';

use Amp\Http\Client\HttpClientBuilder;
use Amp\Loop;
use Amp\Producer;
use Amp\Sync\LocalSemaphore;
use app\Telegram;
use Monolog\ErrorHandler;
use Monolog\Handler\TelegramBotHandler;
use Monolog\Logger;
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

Loop::run(function () {
    $iterator = new Producer(function ($emit) {
        $file = fopen('data/domains.txt', 'r');
        try {
            for ($i = 0; false !== $line = fgets($file); $i++) {
                yield $emit(trim($line));
            }
        } finally {
            Log::debug("END OF FILE AT LINE {$i}");
        }
    });

    $iterator = filter($iterator, function ($line) {
        return isValidName($line);
    });

    $parser = new Parser();

    yield each($iterator, new LocalSemaphore(50), $parser);

    (new Telegram())->sendMessage('Чекер закончил работу.');
});

Log::debug('DONE');