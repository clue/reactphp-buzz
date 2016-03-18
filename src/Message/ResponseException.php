<?php

namespace Clue\React\Buzz\Message;

use RuntimeException;
use Psr\Http\Message\ResponseInterface;

/**
 * A ResponseException will be returned for valid Response objects that use an HTTP error code
 *
 * You can access the original ResponseInterface object via its getter.
 */
class ResponseException extends RuntimeException
{
    private $response;

    public function __construct(ResponseInterface $response, $message = null, $code = null, $previous = null)
    {
        if ($message === null) {
            $message = 'HTTP status code ' . $response->getStatusCode() . ' (' . $response->getReasonPhrase() . ')';
        }
        if ($code === null) {
            $code = $response->getStatusCode();
        }
        parent::__construct($message, $code, $previous);

        $this->response = $response;
    }

    /**
     * get Response message object
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }
}
