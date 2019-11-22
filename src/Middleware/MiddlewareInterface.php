<?php

namespace Clue\React\Buzz\Middleware;

use Psr\Http\Message\RequestInterface;

/**
 * Interface MiddlewareInterface
 *
 * @package Clue\React\Buzz\Middleware
 */
interface MiddlewareInterface
{

    /**
     * Handle a request.
     *
     * End this function by calling:
     *   <code>
     *      return $next($request);
     *   </code
     *
     * @param RequestInterface $request
     * @param callable $next Next middleware
     */
    public function handleRequest(RequestInterface $request, callable $next);
}