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

    private $numRequests = 0;

    // context: http.follow_location
    private $followRedirects = false;

    // context: http.max_redirects
    private $maxRedirects = 10;

    // context: http.ignore_errors
    private $obeySuccessCode = true;

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
        ++$this->numRequests;

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

        if ($this->followRedirects && ($response->getCode() >= 300 && $response->getCode() < 400 && $location = $response->getHeader('Location'))) {
            // naïve approach..
            $request = new Request('GET', $location);

            $this->progress('redirect', array($request));

            if ($this->numRequests >= $this->maxRedirects) {
                throw new \RuntimeException('Maximum number of redirects (' . $this->maxRedirects . ') exceeded');
            }

            return $this->next($request);
        }

        // only status codes 200-399 are considered to be valid, reject otherwise
        if ($this->obeySuccessCode && ($response->getCode() < 200 || $response->getCode() >= 400)) {
            throw new \RuntimeException('HTTP status code ' . $response->getCode() . ' (' . $response->getReasonPhrase() . ')', $response->getCode());
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
