<?php

namespace Clue\React\Buzz\Message;

use RuntimeException;

/**
 * A ResponseException will be returned for valid Response objects that use an HTTP error code
 *
 * You can access the original Response object via its getter.
 */
class ResponseException extends RuntimeException
{
    private $response;

    public function __construct(Response $response, $message = null, $code = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->response = $response;
    }

    /**
     * get Response message object
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}
