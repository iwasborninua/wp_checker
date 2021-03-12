<?php


namespace app;


use Amp\Dns\DnsException;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;

class Brute
{
    protected $client;
    protected $dir;

    private $url;
    private $login;
    private $password;

    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->build();
    }

    public function __invoke($data)
    {
        $this->loginCheck($data);
        $this->password = $data['password'];

        if ($this->login != null) {
            $this->wpRequest(explode(';', $data['url'])[0]);
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

    public function wpRequest($url) {
        $request = new Request($url);
        $request->setTcpConnectTimeout(2400);

        $response = yield $this->client->request($request);

        echo "<pre>";
        print_r('121212121212');die;
    }
}