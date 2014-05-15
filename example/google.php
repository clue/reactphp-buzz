<?php

use Clue\React\Buzz\Message\Response\BufferedResponse;
use Clue\React\Buzz\Browser;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$client->head('http://www.github.com/clue/http-react')->then(function (BufferedResponse $result) {
    echo $result->getStatusLine() . PHP_EOL;
    var_dump($result->getHeaders(), $result->getBody());
});

$client->get('http://google.com')->then(function (BufferedResponse $response) {
    echo $response->getStatusLine() . PHP_EOL;
    var_dump($response->getHeaders(), $response->getBody());
});

$client->get('http://www.lueck.tv/psocksd')->then(function (BufferedResponse $response) {
    echo $response->getStatusLine() . PHP_EOL;
    var_dump($response->getHeaders(), $response->getBody());
});

$loop->run();
