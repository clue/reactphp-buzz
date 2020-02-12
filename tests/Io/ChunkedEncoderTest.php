<?php

namespace Clue\Tests\React\Buzz\Io;

use Clue\React\Buzz\Io\ChunkedEncoder;
use PHPUnit\Framework\TestCase;
use React\Stream\ThroughStream;

class ChunkedEncoderTest extends TestCase
{
    private $input;
    private $chunkedStream;

    public function setUp()
    {
        $this->input = new ThroughStream();
        $this->chunkedStream = new ChunkedEncoder($this->input);
    }

    public function testChunked()
    {
        $this->chunkedStream->on('data', $this->expectCallableOnceWith("5\r\nhello\r\n"));
        $this->input->emit('data', array('hello'));
    }

    public function testEmptyString()
    {
        $this->chunkedStream->on('data', $this->expectCallableNever());
        $this->input->emit('data', array(''));
    }

    public function testBiggerStringToCheckHexValue()
    {
        $this->chunkedStream->on('data', $this->expectCallableOnceWith("1a\r\nabcdefghijklmnopqrstuvwxyz\r\n"));
        $this->input->emit('data', array('abcdefghijklmnopqrstuvwxyz'));
    }

    public function testHandleClose()
    {
        $this->chunkedStream->on('close', $this->expectCallableOnce());

        $this->input->close();

        $this->assertFalse($this->chunkedStream->isReadable());
    }

    public function testHandleError()
    {
        $this->chunkedStream->on('error', $this->expectCallableOnce());
        $this->chunkedStream->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($this->chunkedStream->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedEncoder($input);
        $parser->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedEncoder($input);
        $parser->pause();
        $parser->resume();
    }

    public function testPipeStream()
    {
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $ret = $this->chunkedStream->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceWith($value)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($value);

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function createCallableMock()
    {
        return $this->getMockBuilder('stdClass')->setMethods(array('__invoke'))->getMock();
    }
}
