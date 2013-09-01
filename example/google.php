<?php

use Clue\Http\React\Factory;
use Clue\Http\React\Client\Message\Response\BufferedResponse;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$client->get('http://www.google.com/')->then(function (BufferedResponse $result) {
    var_dump($result->getHeaders(), $result->getBody());
});

$loop->run();
