<?php

require "vendor/autoload.php";
require "lib/functions.php";

$domains  = fopen("data/domains.txt", 'r+');
$verified = fopen("data/verified.txt", 'w+');
$bad      = fopen("data/bad.txt", 'w+');

$client = new GuzzleHttp\Client();

while (false !== $line = fgets($domains)) {
    $line = trim($line);
    $login_url = DP . $line . WP_ADMIN_PATH;

    try {
        $response = $client->request('GET', $login_url, [
            'allow_redirects' => false,
            'max' => '2'
        ]);
    } catch (Exception $e) {
        fwrite($bad, $line . PHP_EOL);
        echo "bad request" . $line . PHP_EOL;
        continue;
    }

    if ($response->getStatusCode() == 200) {
        $url = DP . $line . AUTHOR_DORK;

        try {
            $response = $client->request('GET', $url, [
                'allow_redirects' => false,
                'max' => '2'
            ]);
        } catch (Exception $e) {
            echo "code: " . $e->getCode() . " " . $line . PHP_EOL;
            fwrite($bad, $line . PHP_EOL);
        }

        if ($response->getStatusCode() == 301) {
            $location = $response->getHeader("Location")[0];
            $location_array = array_diff(explode('/', $location), LOCATION_URL_RULES);

            if (count($location_array) == 2) {
                $url = DP . $line . "/wp-login.php;" . array_pop($location_array);
            } else {
                $url = DP . $line . WP_ADMIN_PATH;
            }

            fwrite($verified, $url . PHP_EOL);
            echo "verifed: " . $url . PHP_EOL;
        } elseif ($response->getStatusCode() == "200") {
            $url = DP . $line . WP_ADMIN_PATH;
            fwrite($verified, $url . PHP_EOL);
            echo $url . ": 200" . PHP_EOL;
        } else {
            echo $line . ": bad" . PHP_EOL;
            fwrite($bad, $line . PHP_EOL);
        }
    }
}