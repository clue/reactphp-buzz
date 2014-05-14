<?php

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Response\DownloadResponse;
use React\EventLoop\StreamSelectLoop;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$client->download('http://www.google.com/', '/var/tmp/asd')->then(function (DownloadResponse $response) {
    var_dump($response);
});

$loop->run();
