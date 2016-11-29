<?php

use Clue\React\Buzz\Io\Sender;
use React\SocketClient\TcpConnector;
use React\EventLoop\Factory as LoopFactory;
use Clue\React\Socks\Client as SocksClient;
use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use RingCentral\Psr7;

require __DIR__ . '/vendor/autoload.php';

$loop = LoopFactory::create();

// create a new SOCKS client which connects to a SOCKS server listening on localhost:9050
// not already running a SOCKS server? Try this: ssh -D 9050 localhost
$socks = new SocksClient('127.0.0.1:9050', new TcpConnector($loop));

// create a Browser object that uses the SOCKS client for connections
$sender = Sender::createFromLoopConnectors($loop, $socks);
$browser = new Browser($loop, $sender);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('https://www.google.com/')->then(function (ResponseInterface $response) {
    echo Psr7\str($response);
}, 'printf');

$loop->run();
