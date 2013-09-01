<?php

use Clue\Http\React\Factory;
use Clue\Http\React\Client\Message\Response\DownloadResponse;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$client->download('http://www.google.com/', '/var/tmp/asd')->then(function (DownloadResponse $response) {
    var_dump($response);
});

$loop->run();
