<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use React\Stream\ReadableResourceStream;
use RingCentral\Psr7;

$url = isset($argv[1]) ? $argv[1] : 'https://httpbin.org/post';

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$in = new ReadableResourceStream(STDIN, $loop);

echo 'Sending STDIN as POST to ' . $url . '…' . PHP_EOL;

$client->post($url, array(), $in)->then(function (ResponseInterface $response) {
    echo 'Received' . PHP_EOL . Psr7\str($response);
}, 'printf');

$loop->run();
