<?php

use Amp\Promise;
use Amp\Dns;

define('WP_ADMIN_PATH', '/wp-login.php');
define('DP', 'http://');
define('AUTHOR_DORK', '/?author=1');
define('LOGIN_MACROS', '{login}');
define('LOCATION_URL_RULES', ['', 'http:', 'https:', '?author=1', 'author']);

// чем больше тем лучше.
$dns_ips = [
    '8.8.8.8:53',
    '208.67.222.222:53',
    '208.67.220.220:53',
    '209.244.0.3:53',
    '209.244.0.4:53',
    '4.2.2.1:53',
    '4.2.2.2:53',
    '4.2.2.3:53',
    '4.2.2.4:53',
];

function trimRow($item) {
    return trim($item);
}

function getTest(&$hui) {
    print_r(array(current($hui)));
    if (next($hui) == null)
        reset($hui);
}

function setDNS(&$dns_ips) {
    $current_dns = array(current($dns_ips));

    Dns\resolver(new Dns\Rfc1035StubResolver(null, new class implements Dns\ConfigLoader {
        public function loadConfig(): Promise
        {
            return Amp\call(function ($current_dns) {
                $hosts = yield (new Dns\HostLoader)->loadHosts();

                return new Dns\Config($current_dns, $hosts, $timeout = 5000, $attempts = 2);
            });
        }
    }));

    if (next($dns_ips) == null)
        reset($dns_ips);

    echo "Set DNS config \n";
}