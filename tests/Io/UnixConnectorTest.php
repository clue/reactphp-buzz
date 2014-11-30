<?php

use Clue\React\Buzz\Io\UnixConnector;
use React\EventLoop\Factory as LoopFactory;

class UnixConnectorTest extends TestCase
{
    public function testInvalid()
    {
        $path = 'invalid://asd';

        $loop = $this->getMock('React\EventLoop\LoopInterface');
        $connector = new UnixConnector($loop, $path);

        $promise = $connector->create('localhost', 80);

        $this->expectPromiseReject($promise);
    }

    public function testValid()
    {
        $path = tempnam(sys_get_temp_dir(), 'socket');

        $server = @stream_socket_server('unix://' . $path, $errno, $errstr);

        if (!$server) {
            $this->markTestSkipped('Unable to create socket: ' . $errstr . '(' . $errno .')');
            return;
        }

        $loop = $this->getMock('React\EventLoop\LoopInterface');
        $connector = new UnixConnector($loop, 'unix://' . $path);

        $promise = $connector->create('localhost', 80);

        $this->expectPromiseResolve($promise);

        fclose($server);
        unlink($path);
    }
}
