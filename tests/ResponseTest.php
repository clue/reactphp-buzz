<?php

use Clue\React\Buzz\Message\Response;

class ReponseTest extends TestCase
{
    public function testCtor()
    {
        $response = new Response(1.1, 200, 'OK');

        $this->assertEquals(200, $response->getCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('HTTP/1.1 200 OK', $response->getStatusLine());
    }
}
