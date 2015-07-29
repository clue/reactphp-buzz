<?php

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$client->head('http://www.github.com/clue/http-react')->then(function (Response $result) {
    var_dump($result->getHeaders(), $result->getBody());
});

$client->get('http://google.com')->then(function (Response $response) {
    var_dump($response->getHeaders(), $response->getBody());
});

$client->get('http://www.lueck.tv/psocksd')->then(function (Response $response) {
    var_dump($response->getHeaders(), $response->getBody());
});

$loop->run();
