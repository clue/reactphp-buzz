<?php

use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\ResponseException;
use Psr\Http\Message\RequestInterface;
use RingCentral\Psr7\Response;
use Clue\React\Buzz\Message\MessageFactory;
use React\Promise;
use Clue\React\Block;
use React\EventLoop\Factory;
use React\Stream\ThroughStream;

class TransactionTest extends TestCase
{
    public function testReceivingErrorResponseWillRejectWithResponseException()
    {
        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = new Response(404);

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($request, $sender, array(), new MessageFactory());
        $promise = $transaction->send();

        try {
            Block\await($promise, $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock());
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

        $stream = new ThroughStream();
        $loop->addTimer(0.001, function () use ($stream) {
            $stream->emit('data', array('hello world'));
            $stream->close();
        });

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
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

    /**
     * @expectedException RuntimeException
     */
    public function testCancelBufferingResponseWillCloseStreamAndReject()
    {
        $messageFactory = new MessageFactory();
        $loop = Factory::create();

        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $stream->expects($this->any())->method('isReadable')->willReturn(true);
        $stream->expects($this->once())->method('close');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = $messageFactory->response(1.0, 200, 'OK', array(), $stream);

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($request, $sender, array(), $messageFactory);
        $promise = $transaction->send();
        $promise->cancel();

        Block\await($promise, $loop);
    }

    public function testReceivingStreamingBodyWillResolveWithStreamingResponseIfStreamingIsEnabled()
    {
        $messageFactory = new MessageFactory();

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = $messageFactory->response(1.0, 200, 'OK', array(), $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock());

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($request, $sender, array('streaming' => true), $messageFactory);
        $promise = $transaction->send();

        $response = Block\await($promise, $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', (string)$response->getBody());
    }

    public function testFollowingRedirectWithSpecifiedHeaders()
    {
        $messageFactory = new MessageFactory();

        $requestWithUserAgent = $messageFactory->request('GET', 'http://example.com', ['User-Agent' => 'Chrome']);
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithUserAgent
        $redirectResponse = $messageFactory->response(1.0, 301, null);
        $sender->expects($this->at(0))->method('send')->willReturn(Promise\resolve($redirectResponse));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithUserAgent
        $okResponse = $messageFactory->response(1.0, 200, 'OK');
        $sender->expects($this->at(1))->method('send')
            ->with($this->callback(function(RequestInterface $request){
                $this->assertEquals(['Chrome'], $request->getHeader('User-Agent'));
                return true;
            }))
            ->willReturn(Promise\resolve($okResponse));

        $transaction = new Transaction($requestWithUserAgent, $sender, array(), $messageFactory);
        $transaction->send();
    }
}
