<?php
    require "vendor/autoload.php";

    $client = new GuzzleHttp\Client();

    $domains   = fopen("verified.txt", 'r+') or die("File verified.txt not exists!");
    $logins    = file('logins.txt') or die("File logins.txt not exists!");
    $passwords = file('passwords.txt') or die("File passwords.txt not exists!");

    array_walk($logins, function (&$i, $k) {
        print_r($i);die;
        return $i . '121212';
    });

    echo "<pre>";
    var_dump($logins[0]);die;

    foreach ($logins as $login) {
        $login = trim($login);
        foreach ($passwords as $password) {
//           $password = trim($password);
           $domain = fgets($domains);
           $hui = null;
           var_dump($password == "{login}");die;
           $password == '{login}' && isset(explode(';', $domain)[1]) ? $hui = 'true' : $hui = 'false';
            echo $hui;die();
        }
    }
