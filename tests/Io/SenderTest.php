<?php

use Clue\React\Buzz\Io\Sender;
use React\Promise\Deferred;
use Guzzle\Common\Exception\RuntimeException;
use Clue\React\Buzz\Message\Request;

class SenderTest extends TestCase
{
    public function testCreateFromLoop()
    {
        $loop = $this->getMock('React\EventLoop\LoopInterface');

        $sender = Sender::createFromLoop($loop);

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    public function testCreateFromLoopConnectors()
    {
        $loop = $this->getMock('React\EventLoop\LoopInterface');
        $connector = $this->getMock('React\SocketClient\ConnectorInterface');

        $sender = Sender::createFromLoopConnectors($loop, $connector);

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    public function testSenderRejection()
    {
        $loop = $this->getMock('React\EventLoop\LoopInterface');

        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector->expects($this->once())->method('create')->will($this->returnValue($this->createRejected(new RuntimeException('Rejected'))));

        $sender = Sender::createFromLoopConnectors($loop, $connector);

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
