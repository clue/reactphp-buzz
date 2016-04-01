<?php

use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\ResponseException;
use RingCentral\Psr7\Response;
use Clue\React\Buzz\Message\MessageFactory;
use React\Promise;
use Clue\React\Block;
use React\EventLoop\Factory;
use React\Stream\ReadableStream;

class TransactionTest extends TestCase
{
    public function testReceivingErrorResponseWillRejectWithResponseException()
    {
        $request = $this->getMock('Psr\Http\Message\RequestInterface');
        $response = new Response(404);

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($request, $sender, array(), new MessageFactory());
        $promise = $transaction->send();

        try {
            Block\await($promise, $this->getMock('React\EventLoop\LoopInterface'));
            $this->fail();
        } catch (ResponseException $exception) {
            $this->assertEquals(404, $exception->getCode());
            $this->assertSame($response, $exception->getResponse());
        }
    }

    public function testReceivingStreamingBodyWillResolveWithBufferedResponseByDefault()
    {
        $messageFactory = new MessageFactory();
        $loop = Factory::create();

        $stream = new ReadableStream();
        $loop->addTimer(0.001, function () use ($stream) {
            $stream->emit('data', array('hello world'));
            $stream->close();
        });

        $request = $this->getMock('Psr\Http\Message\RequestInterface');
        $response = $messageFactory->response(1.0, 200, 'OK', array(), $stream);

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($request, $sender, array(), $messageFactory);
        $promise = $transaction->send();

        $response = Block\await($promise, $loop);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('hello world', (string)$response->getBody());
    }

    public function testReceivingStreamingBodyWillResolveWithStreamingResponseIfStreamingIsEnabled()
    {
        $messageFactory = new MessageFactory();

        $request = $this->getMock('Psr\Http\Message\RequestInterface');
        $response = $messageFactory->response(1.0, 200, 'OK', array(), $this->getMock('React\Stream\ReadableStreamInterface'));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($request, $sender, array('streaming' => true), $messageFactory);
        $promise = $transaction->send();

        $response = Block\await($promise, $this->getMock('React\EventLoop\LoopInterface'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', (string)$response->getBody());
    }
}
