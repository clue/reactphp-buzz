<?php

use Clue\React\Buzz\Io\Sender;
use React\Promise\Deferred;
use Guzzle\Common\Exception\RuntimeException;
use Clue\React\Buzz\Message\Request;

class SenderTest extends TestCase
{
    private $loop;

    public function setUp()
    {
        $this->loop = $this->getMock('React\EventLoop\LoopInterface');
    }

    public function testCreateFromLoop()
    {
        $sender = Sender::createFromLoop($this->loop);

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    public function testCreateFromLoopConnectors()
    {
        $connector = $this->getMock('React\SocketClient\ConnectorInterface');

        $sender = Sender::createFromLoopConnectors($this->loop, $connector);

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    public function testSenderRejection()
    {
        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector->expects($this->once())->method('create')->will($this->returnValue($this->createRejected(new RuntimeException('Rejected'))));

        $sender = Sender::createFromLoopConnectors($this->loop, $connector);

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request);

        $this->expectPromiseReject($promise);
    }

    private function createRejected($value)
    {
        $deferred = new Deferred();
        $deferred->reject($value);
        return $deferred->promise();
    }
}
