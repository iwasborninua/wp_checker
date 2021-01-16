<?php

namespace app\dns;

use Amp\Promise;
use Amp\Dns\ConfigLoader as ConfigLoaderInterface;
use Amp\Dns\Config;
use Amp\Dns\HostLoader;
use function Amp\call;

class ConfigLoader implements ConfigLoaderInterface
{
    protected $dns;

    public function __construct(array $dns)
    {
        $this->dns = $dns;
    }

    public function loadConfig() : Promise
    {
        return call(function () {
            $hosts = yield (new HostLoader)->loadHosts();

            return new Config($this->dns, $hosts, $timeout = 10000, $attempts = 10);
        });
    }
}
