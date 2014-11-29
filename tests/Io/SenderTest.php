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
}
