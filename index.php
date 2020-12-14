<?php
    require "vendor/autoload.php";
    require "lib/functions.php";

    $client = new GuzzleHttp\Client();

    $domains   = fopen("verified.txt", 'r+') or die("File verified.txt not exists!");
    $logins    = file('logins.txt') or die("File logins.txt not exists!");
    $passwords = file('passwords.txt') or die("File passwords.txt not exists!");

    $logins = array_map('trimRow', $logins);
    $passwords = array_map('trimRow', $passwords);

    foreach ($logins as $login) {
        foreach ($passwords as $password) {

        }
    }
