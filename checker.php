<?php

require "vendor/autoload.php";

$domains = fopen("domains.txt", 'r+');
$verified = fopen("verified.txt", 'w+');
$bad = fopen("bad.txt", 'w+');

$client = new GuzzleHttp\Client();
$count = 1;

while (false !== $line = fgets($domains)) {
    $line = trim($line);
    $uri = 'http://' . $line  . "/?author=1";

    try {
        $response = $client->request('GET', $uri, [
            'allow_redirects' => false,
            'max' => '2'
            ]);
    } catch (Exception $e) {
        echo $line . ": " . $e->getCode() . PHP_EOL;
        fwrite($bad, $line . PHP_EOL);
    }

    if ($response->getStatusCode() == "301") {
        $location = $response->getHeader("Location")[0];
        $location_array = array_diff(explode('/', $location), ['', 'http:', '?author=1']);

        if (count($location_array) <= 3) {
            $uri = "http://" . $line . "/wp-login.php";
        } else {
            $uri = "http://" . $line . "/wp-login.php;" . array_pop($location_array);
        }

        fwrite($verified, $uri . PHP_EOL);
        echo $uri . " :301" . PHP_EOL;

    } elseif ($response->getStatusCode() == "200") {
        $uri = "http://" . $line . "/wp-login.php";
        fwrite($verified, $uri . PHP_EOL);
        echo $uri . ": 200" . PHP_EOL;
    } else {
        echo $line . ": bad" . PHP_EOL;
        fwrite($bad, $line . PHP_EOL);
    }
}