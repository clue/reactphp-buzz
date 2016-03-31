<?php

use Clue\React\Buzz\Browser;
use React\Stream\ReadableStreamInterface;
use Psr\Http\Message\ResponseInterface;
use React\Stream\Stream;
use RingCentral\Psr7;

$url = isset($argv[1]) ? $argv[1] : 'http://google.com/';

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$out = new Stream(STDOUT, $loop);
$out->pause();

$info = new Stream(STDERR, $loop);
$info->pause();

$info->write('Requesting ' . $url . '…' . PHP_EOL);

$client->withOptions(array('streaming' => true))->get($url)->then(function (ResponseInterface $response) use ($info, $out) {
    $info->write('Received' . PHP_EOL . Psr7\str($response));

    $response->getBody()->pipe($out);
}, 'printf');

$loop->run();
