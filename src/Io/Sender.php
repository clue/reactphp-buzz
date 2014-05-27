<?php

namespace Clue\React\Buzz\Io;

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Request;
use Clue\React\Buzz\Message\Response;
use React\HttpClient\Request as RequestStream;
use React\HttpClient\Response as ResponseStream;
use React\Promise\Deferred;
use Clue\React\Buzz\Message\Headers;
use Clue\React\Buzz\Message\Body;

class Sender
{
    private $browser;

    public function __construct(Browser $browser)
    {
        $this->browser = $browser;
    }

    public function send(Request $request)
    {
        $body = $request->getBody();
        if (!$body->isEmpty()) {
            //$request->setHeader('Content-Length', $body->getLength());
        }

        $deferred = new Deferred();

        $requestStream = $this->browser->getClient()->request($request->getMethod(), $request->getUrl(), $request->getHeaders()->getAll());
        $requestStream->end((string)$body);

        $requestStream->on('error', function($error) use ($deferred) {
            $deferred->reject($error);
        });

        $requestStream->on('response', function (ResponseStream $response) use ($deferred) {
            $bodyBuffer = '';
            $response->on('data', function ($data) use (&$bodyBuffer) {
                $bodyBuffer .= $data;
                // progress
            });

            $response->on('end', function ($error = null) use ($deferred, $response, &$bodyBuffer) {
                if ($error !== null) {
                    $deferred->reject($error);
                } else {
                    $deferred->resolve(new Response(
                        'HTTP/' . $response->getVersion(),
                        $response->getCode(),
                        $response->getReasonPhrase(),
                        new Headers($response->getHeaders()),
                        new Body($bodyBuffer)
                    ));
                }
            });
        });

        return $deferred->promise();
    }
}
