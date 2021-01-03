<?php

namespace app\dns;

use Amp\Promise;
use Amp\Dns\Resolver as ResolverInterface;
use Amp\Dns\Rfc1035StubResolver as DnsResolver;

class Resolver implements ResolverInterface
{
    protected $resolvers = [];

    public function __construct()
    {
        $this->resolvers[] = new DnsResolver(null, new ConfigLoader(['1.1.1.1:53', '1.0.0.1:53']));
        $this->resolvers[] = new DnsResolver(null, new ConfigLoader(['8.8.8.8:53', '8.8.4.4:53']));
    }

    public function getResolver() : ResolverInterface
    {
        return $this->resolvers[array_rand($this->resolvers)];
    }

    public function resolve(string $name, int $typeRestriction = null) : Promise
    {
        return $this->getResolver()->resolve($name, $typeRestriction);
    }

    public function query(string $name, int $type) : Promise
    {
        return $this->getResolver()->resolve($name, $typeRestriction);
    }
}
