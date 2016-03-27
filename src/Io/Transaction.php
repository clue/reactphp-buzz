<?php

namespace Clue\React\Buzz\Io;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Exception;
use Clue\React\Buzz\Browser;
use React\HttpClient\Client as HttpClient;
use Clue\React\Buzz\Io\Sender;
use Clue\React\Buzz\Message\ResponseException;
use Clue\React\Buzz\Message\MessageFactory;

/**
 * @internal
 */
class Transaction
{
    private $browser;
    private $request;
    private $messageFactory;

    private $numRequests = 0;

    // context: http.follow_location
    private $followRedirects = true;

    // context: http.max_redirects
    private $maxRedirects = 10;

    // context: http.ignore_errors
    private $obeySuccessCode = true;

    public function __construct(RequestInterface $request, Sender $sender, array $options = array(), MessageFactory $messageFactory)
    {
        foreach ($options as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }

        $this->request = $request;
        $this->sender = $sender;
        $this->messageFactory = $messageFactory;
    }

    public function send()
    {
        return $this->next($this->request);
    }

    protected function next(RequestInterface $request)
    {
        $this->progress('request', array($request));

        $that = $this;
        ++$this->numRequests;

        return $this->sender->send($request, $this->messageFactory)->then(
            function (ResponseInterface $response) use ($request, $that) {
                return $that->onResponse($response, $request);
            },
            function ($error) use ($request, $that) {
                return $that->onError($error, $request);
            }
        );
    }

    /**
     * @internal
     * @param ResponseInterface $response
     * @param RequestInterface $request
     * @throws ResponseException
     * @return ResponseInterface
     */
    public function onResponse(ResponseInterface $response, RequestInterface $request)
    {
        $this->progress('response', array($response, $request));

        if ($this->followRedirects && ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400)) {
            return $this->onResponseRedirect($response, $request);
        }

        // only status codes 200-399 are considered to be valid, reject otherwise
        if ($this->obeySuccessCode && ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400)) {
            throw new ResponseException($response);
        }

        // resolve our initial promise
        return $response;
    }

    /**
     * @internal
     * @param Exception $error
     * @param RequestInterface $request
     * @throws Exception
     */
    public function onError(Exception $error, RequestInterface $request)
    {
        $this->progress('error', array($error, $request));

        throw $error;
    }

    private function onResponseRedirect(ResponseInterface $response, RequestInterface $request)
    {
        // resolve location relative to last request URI
        $location = $this->messageFactory->uriRelative($request->getUri(), $response->getHeaderLine('Location'));

        // naïve approach..
        $method = ($request->getMethod() === 'HEAD') ? 'HEAD' : 'GET';
        $request = $this->messageFactory->request($method, $location);

        $this->progress('redirect', array($request));

        if ($this->numRequests >= $this->maxRedirects) {
            throw new \RuntimeException('Maximum number of redirects (' . $this->maxRedirects . ') exceeded');
        }

        return $this->next($request);
    }

    private function progress($name, array $args = array())
    {
        return;

        echo $name;

        foreach ($args as $arg) {
            echo ' ';
            if ($arg instanceof ResponseInterface) {
                echo 'HTTP/' . $arg->getProtocolVersion() . ' ' . $arg->getStatusCode() . ' ' . $arg->getReasonPhrase();
            } elseif ($arg instanceof RequestInterface) {
                echo $arg->getMethod() . ' ' . $arg->getRequestTarget() . ' HTTP/' . $arg->getProtocolVersion();
            } else {
                echo $arg;
            }
        }

        echo PHP_EOL;
    }
}
