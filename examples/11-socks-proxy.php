<?php

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Io\Sender;
use Clue\React\Socks\Client as SocksClient;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Connector;
use RingCentral\Psr7;

require __DIR__ . '/../vendor/autoload.php';

$loop = LoopFactory::create();

// create a new SOCKS client which connects to a SOCKS server listening on localhost:1080
// not already running a SOCKS server? Try this: ssh -D 1080 localhost
$proxy = new SocksClient('127.0.0.1:1080', new Connector($loop));

// create a Browser object that uses the SOCKS client for connections
$connector = new Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));
$sender = Sender::createFromLoop($loop, $connector);
$browser = new Browser($loop, $sender);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('https://www.google.com/')->then(function (ResponseInterface $response) {
    echo Psr7\str($response);
}, 'printf');

$loop->run();
