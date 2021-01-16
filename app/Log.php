<?php

namespace app;

use Amp\ByteStream;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Logger;

class Log
{
	protected static $instance;

	public static function getLogger()
	{
		if (!static::$instance) {
			static::configureInstance();
		}

		return static::$instance;
	}

	protected static function configureInstance()
	{
        $handler = new StreamHandler(ByteStream\getStdout());
        $handler->setFormatter(new ConsoleFormatter(ConsoleFormatter::DEFAULT_FORMAT, 'Y-m-d H:i:s'));

        $logger = new Logger('app');
        $logger->pushHandler($handler);

		static::$instance = $logger;
	}

    public static function __callStatic(string $name, array $arguments)
    {
        $trace = debug_backtrace();
        $callee = array_shift($trace);

        $arguments[1] = $arguments[1] ?? [];
        $arguments[1]['mem'] = round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB';
        $arguments[1]['callee'] = basename($callee['file']) . ":{$callee['line']}";
        return static::getLogger()->{$name}(...$arguments);
    }
}
