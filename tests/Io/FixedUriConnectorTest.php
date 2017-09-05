<?php

use Clue\React\Buzz\Io\FixedUriConnector;

class FixedUriConnectorTest extends PHPUnit_Framework_TestCase
{
    public function testWillInvokeGivenConnector()
    {
        $base = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $base->expects($this->once())->method('connect')->with('test')->willReturn('ret');

        $connector = new FixedUriConnector('test', $base);

        $this->assertEquals('ret', $connector->connect('ignored'));
    }
}
