<?php

use Clue\React\Buzz\Message\Headers;

class HeadersTest extends TestCase
{
    public function testEmpty()
    {
        $headers = new Headers();

        $this->assertEquals(array(), $headers->getAll());
        $this->assertEquals(array(), $headers->getHeaderValues('Host'));
        $this->assertEquals(null, $headers->getHeaderValue('Host'));
    }

    public function testHeaders()
    {
        $all = array(
            'Host' => 'localhost',
            'Custom' => array('first', 'second')
        );

        $headers = new Headers($all);

        $this->assertEquals($all, $headers->getAll());

        $this->assertEquals('localhost', $headers->getHeaderValue('Host'));
        $this->assertEquals(array('localhost'), $headers->getHeaderValues('Host'));

        $this->assertEquals('first', $headers->getHeaderValue('custom'));
        $this->assertEquals(array('first', 'second'), $headers->getHeaderValues('custom'));
    }
}
