<?php

namespace Clue\React\Buzz\Message;

use Clue\React\Buzz\Message\Request\Request;
use Clue\React\Buzz\Message\Response\BufferedResponse;
use Exception;
use Clue\React\Buzz\Browser;
use React\HttpClient\Client as HttpClient;

class Transaction
{
    private $browser;
    private $request;

    public function __construct(Request $request, Browser $browser)
    {
        $this->request = $request;
        $this->browser = $browser;
    }

    public function send($content = null)
    {
        return $this->next($this->request, $content);
    }

    protected function next(Request $request, $content = null)
    {
        $this->progress('request', array($request));

        $that = $this;

        return $request->send($this->browser->getClient(), $content)->then(
            function (BufferedResponse $response) use ($request, $that) {
                return $that->onResponse($response, $request);
            },
            function ($error) use ($request, $that) {
                return $that->onError($error, $request);
            }
        );
    }

    public function onResponse(BufferedResponse $response, Request $request)
    {
        $this->progress('response', array($response, $request));

        if ($response->getCode() >= 300 && $response->getCode() < 400 && $location = $response->getHeader('Location')) {
            // naïve approach..
            $request = new Request('GET', $location);

            $this->progress('redirect', array($request));

            return $this->next($request);
        }

        // resolve our initial promise
        return $response;
    }

    public function onError(Exception $error, Request $request)
    {
        $this->progress('error', array($error, $request));

        throw $error;
    }

    private function progress($name, array $args = array())
    {
        return;

        echo $name;

        foreach ($args as $arg) {
            echo ' ';
            if ($arg instanceof BufferedResponse) {
                echo $arg->getStatusLine();
            } elseif ($arg instanceof Request) {
                echo $arg->getRequestLine();
            } else {
                echo $arg;
            }
        }

        echo PHP_EOL;
    }
}
