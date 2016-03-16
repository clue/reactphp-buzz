<?php

use Clue\React\Buzz\Io\Sender;
use RingCentral\Psr7\Request;
use React\Promise;
use Clue\React\Block;

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

    public function testCreateFromLoopUnix()
    {
        $sender = Sender::createFromLoopUnix($this->loop, 'unix:///run/daemon.sock');

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    public function testSenderRejection()
    {
        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector->expects($this->once())->method('create')->willReturn(Promise\reject(new RuntimeException('Rejected')));

        $sender = Sender::createFromLoopConnectors($this->loop, $connector);

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request);

        $this->setExpectedException('RuntimeException');
        Block\await($promise, $this->loop);
    }
}
