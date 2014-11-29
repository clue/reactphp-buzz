<?php

use Clue\React\Buzz\Io\Sender;

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
}
