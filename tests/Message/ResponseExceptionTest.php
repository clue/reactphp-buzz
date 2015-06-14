<?php

use Clue\React\Buzz\Message\Response;
use Clue\React\Buzz\Message\ResponseException;

class ResponseExceptionTest extends TestCase
{
    public function testCtorDefaults()
    {
        $response = new Response('HTTP/1.0', 404, 'File not found');
        $e = new ResponseException($response);

        $this->assertEquals(404, $e->getCode());
        $this->assertEquals('HTTP status code 404 (File not found)', $e->getMessage());

        $this->assertSame($response, $e->getResponse());
    }
}
