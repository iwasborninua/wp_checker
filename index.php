<?php
    require "vendor/autoload.php";

    $client = new GuzzleHttp\Client();
    $uri = 'http://wp.loc/wp-login.php';

    $response = $client->request('POST', $uri, [
        'allow_redirects' => false,
        'form_params' => [
            'log' => 'admin',
            'pwd' => '1111',
        ]
    ]);


    echo $response->getStatusCode();