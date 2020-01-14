<?php

namespace Clue\Tests\React\Buzz\Message;

use Clue\React\Buzz\Message\ReadableBodyStream;
use PHPUnit\Framework\TestCase;
use React\Stream\ThroughStream;

class ReadableBodyStreamTest extends TestCase
{
    private $input;
    private $stream;

    public function setUp()
    {
        $this->input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->stream = new ReadableBodyStream($this->input);
    }

    public function testIsReadableIfInputIsReadable()
    {
        $this->input->expects($this->once())->method('isReadable')->willReturn(true);

        $this->assertTrue($this->stream->isReadable());
    }

    public function testIsEofIfInputIsNotReadable()
    {
        $this->input->expects($this->once())->method('isReadable')->willReturn(false);

        $this->assertTrue($this->stream->eof());
    }

    public function testCloseWillCloseInputStream()
    {
        $this->input->expects($this->once())->method('close');

        $this->stream->close();
    }

    public function testCloseWillEmitCloseEvent()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input);

        $called = 0;
        $this->stream->on('close', function () use (&$called) {
            ++$called;
        });

        $this->stream->close();
        $this->stream->close();

        $this->assertEquals(1, $called);
    }

    public function testCloseInputWillEmitCloseEvent()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input);

        $called = 0;
        $this->stream->on('close', function () use (&$called) {
            ++$called;
        });

        $this->input->close();
        $this->input->close();

        $this->assertEquals(1, $called);
    }

    public function testEndInputWillEmitCloseEvent()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input);

        $called = 0;
        $this->stream->on('close', function () use (&$called) {
            ++$called;
        });

        $this->input->end();
        $this->input->end();

        $this->assertEquals(1, $called);
    }

    public function testPauseWillPauseInputStream()
    {
        $this->input->expects($this->once())->method('pause');

        $this->stream->pause();
    }

    public function testResumeWillResumeInputStream()
    {
        $this->input->expects($this->once())->method('resume');

        $this->stream->resume();
    }

    public function testPointlessTostringReturnsEmptyString()
    {
        $this->assertEquals('', (string)$this->stream);
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testPointlessDetachThrows()
    {
        $this->stream->detach();
    }

    public function testPointlessGetSizeReturnsNull()
    {
        $this->assertEquals(null, $this->stream->getSize());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testPointlessTellThrows()
    {
        $this->stream->tell();
    }

    public function testPointlessIsSeekableReturnsFalse()
    {
        $this->assertEquals(false, $this->stream->isSeekable());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testPointlessSeekThrows()
    {
        $this->stream->seek(0);
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testPointlessRewindThrows()
    {
        $this->stream->rewind();
    }

    public function testPointlessIsWritableReturnsFalse()
    {
        $this->assertEquals(false, $this->stream->isWritable());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testPointlessWriteThrows()
    {
        $this->stream->write('');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testPointlessReadThrows()
    {
        $this->stream->read(8192);
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testPointlessGetContentsThrows()
    {
        $this->stream->getContents();
    }

    public function testPointlessGetMetadataReturnsNullWhenKeyIsGiven()
    {
        $this->assertEquals(null, $this->stream->getMetadata('unknown'));
    }

    public function testPointlessGetMetadataReturnsEmptyArrayWhenNoKeyIsGiven()
    {
        $this->assertEquals(array(), $this->stream->getMetadata());
    }
}
