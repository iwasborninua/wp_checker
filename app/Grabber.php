<?php

namespace app;

use Exception;
use Generator;
use Throwable;
use Amp\Dns\DnsException;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Symfony\Component\DomCrawler\Crawler;
use function Amp\asyncCall;

class Grabber
{

    protected $client;
    private   $domains;

    public function __construct()
    {
        $this->client = (new HttpClientBuilder())
            ->retry(10)
            ->build();
    }

    public function __invoke($url)
    {
        echo $url . PHP_EOL;
        $request = new Request($url);
        $request->setTcpConnectTimeout(2400);

        $response = yield $this->client->request($request);


        if ($response->getStatus() == "200") {
            $body = yield $response->getBody()->buffer();
            $domains = (new Crawler($body))->filter('div.left > a:not(.backlink)');

            foreach ($domains as $domain) {
                $this->domains .= $domain->textContent . PHP_EOL;
            }

            file_put_contents('hui.txt', $this->domains, FILE_APPEND);
        }
    }
}
