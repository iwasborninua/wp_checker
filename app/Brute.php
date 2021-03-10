<?php


namespace app;


use Amp\Dns\DnsException;
use Amp\Http\Client\HttpClientBuilder;

class Brute
{
    protected $client;
    protected $dir;

    private $login;
    private $password;

    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->build();

        $dataDir = dirname(__DIR__) . '/data/';
        if (!is_dir($dataDir) || !is_writable($dataDir)) {
            throw new Exception($dataDir . ' is not directory or not writeable');
        }

        $this->dir = $dataDir . date('Y-m-d-h-m');
        mkdir($this->dir, 0777);
    }

    public function __invoke($data)
    {
        $this->loginCheck($data);


//        print_r(explode(';', $data['url']));die;
        print_r($this->login);die;
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
}