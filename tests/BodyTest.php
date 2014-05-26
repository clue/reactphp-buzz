<?php

use Clue\React\Buzz\Message\Body;

class BodyTest extends TestCase
{
    public function testEmpty()
    {
        $body = new Body();

        $this->assertTrue($body->isEmpty());
        $this->assertEquals(0, $body->getLength());
        $this->assertEquals('', (string)$body);
    }

    public function testText()
    {
        $body = new Body('text');

        $this->assertFalse($body->isEmpty());
        $this->assertEquals(4, $body->getLength());
        $this->assertEquals('text', (string)$body);
    }
}
