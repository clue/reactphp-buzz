<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7;

$url = isset($argv[1]) ? $argv[1] : 'http://google.com/';

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

echo 'Requesting ' . $url . '…' . PHP_EOL;

$client->withOptions(array('streaming' => true))->get($url)->then(function (ResponseInterface $response) use ($loop) {
    echo 'Headers received' . PHP_EOL;
    echo Psr7\str($response);

    $stream = $response->getBody();
    if (!$stream instanceof ReadableStreamInterface) {
        throw new UnexpectedValueException();
    }

    // count number of bytes received
    $bytes = 0;
    $stream->on('data', function ($chunk) use (&$bytes) {
        $bytes += strlen($chunk);
    });

    // report progress every 0.1s
    $timer = $loop->addPeriodicTimer(0.1, function () use (&$bytes) {
        echo "\rDownloaded " . $bytes . " bytes…";
    });

    // report results once the stream closes
    $time = microtime(true);
    $stream->on('close', function() use (&$bytes, $timer, $loop, $time) {
        $loop->cancelTimer($timer);

        $time = microtime(true) - $time;

        echo "\r" . 'Downloaded ' . $bytes . ' bytes in ' . round($time, 3) . 's => ' . round($bytes / $time / 1024 / 1024, 1) . ' MiB/s' . PHP_EOL;
    });
}, 'printf');

$loop->run();
