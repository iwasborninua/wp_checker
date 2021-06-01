<?php

namespace app;

use Amp\Http\Client\Body\FormBody;
use Exception;
use Generator;
use Monolog\Logger;
use Throwable;
use Amp\Dns\DnsException;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Symfony\Component\DomCrawler\Crawler;
use function Amp\asyncCall;

class Telegram
{
    private $client;

    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(3)
            ->build();
    }

    public function sendMessage($text = 'testing') {
        $body = new FormBody;
        $body->addField("text", $text);
        $body->addField("chat_id", 165944121);

        $request = new Request('https://api.telegram.org/bot1825782922:AAHYXamDly29xOmHaU3CY2UN73vC7sMlTKU/SendMessage', 'POST');
        $request->setBody($body);

        return $promise = $this->client->request($request);
    }

}
