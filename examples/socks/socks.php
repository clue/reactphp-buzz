<?php

use Clue\React\Buzz\Io\Sender;
use React\HttpClient\Client as HttpClient;
use React\EventLoop\Factory as LoopFactory;
use React\Dns\Resolver\Factory as DnsFactory;
use Clue\React\Socks\Factory as SocksFactory;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Response;

require __DIR__ . '/vendor/autoload.php';

$loop = LoopFactory::create();

// use Google's public DNS server
$dnsResolverFactory = new DnsFactory();
$dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

// create a new SOCKS client which connects to a SOCKS server listening on localhost:9050
// not already running a SOCKS server? Try this: ssh -D 9050 localhost
$factory = new SocksFactory($loop, $dns);
$socks = $factory->createClient('127.0.0.1', 9050);

// create a Browser object that uses the SOCKS client for connections
$http = new HttpClient($loop, $socks->createConnector(), $socks->createSecureConnector());
$sender = new Sender($http);
$browser = new Browser($loop, $sender);

// demo fetching HTTP headers (or bail out otherwise)
$browser->head('https://www.google.com/')->then(function (Response $response) {
    var_dump($response->getHeaders());
}, 'var_dump');

$loop->run();
