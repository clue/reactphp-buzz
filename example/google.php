<?php

use Clue\React\Buzz\Message\Response\BufferedResponse;
use Clue\React\Buzz\Browser;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$client->get('http://www.google.com/')->then(function (BufferedResponse $result) {
    var_dump($result->getHeaders(), $result->getBody());
});

$loop->run();
