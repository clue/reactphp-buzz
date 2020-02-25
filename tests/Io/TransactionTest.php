<?php

namespace Clue\Tests\React\Buzz\Io;

use Clue\React\Block;
use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\MessageFactory;
use Clue\React\Buzz\Message\ResponseException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\Factory;
use React\Promise;
use React\Promise\Deferred;
use React\Stream\ThroughStream;
use RingCentral\Psr7\Response;

class TransactionTest extends TestCase
{
    public function testWithOptionsReturnsNewInstanceWithChangedOption()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $transaction = new Transaction($sender, new MessageFactory(), $loop);

        $new = $transaction->withOptions(array('followRedirects' => false));

        $this->assertInstanceOf('Clue\React\Buzz\Io\Transaction', $new);
        $this->assertNotSame($transaction, $new);

        $ref = new \ReflectionProperty($new, 'followRedirects');
        $ref->setAccessible(true);

        $this->assertFalse($ref->getValue($new));
    }

    public function testWithOptionsDoesNotChangeOriginalInstance()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $transaction = new Transaction($sender, new MessageFactory(), $loop);

        $transaction->withOptions(array('followRedirects' => false));

        $ref = new \ReflectionProperty($transaction, 'followRedirects');
        $ref->setAccessible(true);

        $this->assertTrue($ref->getValue($transaction));
    }

    public function testWithOptionsNullValueReturnsNewInstanceWithDefaultOption()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $transaction = new Transaction($sender, new MessageFactory(), $loop);

        $transaction = $transaction->withOptions(array('followRedirects' => false));
        $transaction = $transaction->withOptions(array('followRedirects' => null));

        $ref = new \ReflectionProperty($transaction, 'followRedirects');
        $ref->setAccessible(true);

        $this->assertTrue($ref->getValue($transaction));
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimer()
    {
        $messageFactory = new MessageFactory();

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutImplicitFromIniWillStartTimeoutTimer()
    {
        $messageFactory = new MessageFactory();

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $messageFactory, $loop);

        $old = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '2');
        $promise = $transaction->send($request);
        ini_set('default_socket_timeout', $old);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillRejectWhenTimerFires()
    {
        $messageFactory = new MessageFactory();

        $timeout = null;
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->callback(function ($cb) use (&$timeout) {
            $timeout = $cb;
            return true;
        }))->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $this->assertNotNull($timeout);
        $timeout();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Request timed out after 2 seconds', $exception->getMessage());
    }

    public function testTimeoutExplicitOptionWillCancelTimeoutTimerWhenSenderResolves()
    {
        $messageFactory = new MessageFactory();

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = $messageFactory->response(1.0, 200, 'OK', array(), '');

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 0.001));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then($this->expectCallableOnceWith($response));
    }

    public function testTimeoutExplicitOptionWillCancelTimeoutTimerWhenSenderRejects()
    {
        $messageFactory = new MessageFactory();

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $exception = new \RuntimeException();

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\reject($exception));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 0.001));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then(null, $this->expectCallableOnceWith($exception));
    }

    public function testTimeoutExplicitNegativeWillNotStartTimeoutTimer()
    {
        $messageFactory = new MessageFactory();

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->never())->method('addTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => -1));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutTimerWhenRequestBodyIsStreaming()
    {
        $messageFactory = new MessageFactory();

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->never())->method('addTimer');

        $stream = new ThroughStream();
        $request = $messageFactory->request('POST', 'http://example.com', array(), $stream);

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimerWhenStreamingRequestBodyIsAlreadyClosed()
    {
        $messageFactory = new MessageFactory();

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $stream = new ThroughStream();
        $stream->close();
        $request = $messageFactory->request('POST', 'http://example.com', array(), $stream);

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimerWhenStreamingRequestBodyCloses()
    {
        $messageFactory = new MessageFactory();

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $stream = new ThroughStream();
        $request = $messageFactory->request('POST', 'http://example.com', array(), $stream);

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $stream->close();

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillRejectWhenTimerFiresAfterStreamingRequestBodyCloses()
    {
        $messageFactory = new MessageFactory();

        $timeout = null;
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->callback(function ($cb) use (&$timeout) {
            $timeout = $cb;
            return true;
        }))->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $stream = new ThroughStream();
        $request = $messageFactory->request('POST', 'http://example.com', array(), $stream);

        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $stream->close();

        $this->assertNotNull($timeout);
        $timeout();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Request timed out after 2 seconds', $exception->getMessage());
    }

    public function testReceivingErrorResponseWillRejectWithResponseException()
    {
        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = new Response(404);
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, new MessageFactory(), $loop);
        $transaction = $transaction->withOptions(array('timeout' => -1));
        $promise = $transaction->send($request);

        try {
            Block\await($promise, $loop);
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
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $promise = $transaction->send($request);

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
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $promise = $transaction->send($request);
        $promise->cancel();

        Block\await($promise, $loop, 0.001);
    }

    public function testReceivingStreamingBodyWillResolveWithStreamingResponseIfStreamingIsEnabled()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = $messageFactory->response(1.0, 200, 'OK', array(), $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock());

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('streaming' => true, 'timeout' => -1));
        $promise = $transaction->send($request);

        $response = Block\await($promise, $loop);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', (string)$response->getBody());
    }

    public function testResponseCode304WithoutLocationWillResolveWithResponseAsIs()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        // conditional GET request will respond with 304 (Not Modified
        $request = $messageFactory->request('GET', 'http://example.com', array('If-None-Match' => '"abc"'));
        $response = $messageFactory->response(1.0, 304, null, array('ETag' => '"abc"'));
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => -1));
        $promise = $transaction->send($request);

        $promise->then($this->expectCallableOnceWith($response));
    }

    public function testCustomRedirectResponseCode333WillFollowLocationHeaderAndSendRedirectedRequest()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        // original GET request will respond with custom 333 redirect status code and follow location header
        $requestOriginal = $messageFactory->request('GET', 'http://example.com');
        $response = $messageFactory->response(1.0, 333, null, array('Location' => 'foo'));
        $sender = $this->makeSenderMock();
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($requestOriginal),
            array($this->callback(function (RequestInterface $request) {
                return $request->getMethod() === 'GET' && (string)$request->getUri() === 'http://example.com/foo';
            }))
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($response),
            new \React\Promise\Promise(function () { })
        );

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction->send($requestOriginal);
    }

    public function testFollowingRedirectWithSpecifiedHeaders()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $customHeaders = array('User-Agent' => 'Chrome');
        $requestWithUserAgent = $messageFactory->request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithUserAgent
        $redirectResponse = $messageFactory->response(1.0, 301, null, array('Location' => 'http://redirect.com'));
        $sender->expects($this->at(0))->method('send')->willReturn(Promise\resolve($redirectResponse));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithUserAgent
        $okResponse = $messageFactory->response(1.0, 200, 'OK');
        $that = $this;
        $sender->expects($this->at(1))
            ->method('send')
            ->with($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertEquals(array('Chrome'), $request->getHeader('User-Agent'));
                return true;
            }))->willReturn(Promise\resolve($okResponse));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction->send($requestWithUserAgent);
    }

    public function testRemovingAuthorizationHeaderWhenChangingHostnamesDuringRedirect()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $customHeaders = array('Authorization' => 'secret');
        $requestWithAuthorization = $messageFactory->request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = $messageFactory->response(1.0, 301, null, array('Location' => 'http://redirect.com'));
        $sender->expects($this->at(0))->method('send')->willReturn(Promise\resolve($redirectResponse));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = $messageFactory->response(1.0, 200, 'OK');
        $that = $this;
        $sender->expects($this->at(1))
            ->method('send')
            ->with($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertFalse($request->hasHeader('Authorization'));
                return true;
            }))->willReturn(Promise\resolve($okResponse));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction->send($requestWithAuthorization);
    }

    public function testAuthorizationHeaderIsForwardedWhenRedirectingToSameDomain()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $customHeaders = array('Authorization' => 'secret');
        $requestWithAuthorization = $messageFactory->request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = $messageFactory->response(1.0, 301, null, array('Location' => 'http://example.com/new'));
        $sender->expects($this->at(0))->method('send')->willReturn(Promise\resolve($redirectResponse));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = $messageFactory->response(1.0, 200, 'OK');
        $that = $this;
        $sender->expects($this->at(1))
            ->method('send')
            ->with($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertEquals(array('secret'), $request->getHeader('Authorization'));
                return true;
            }))->willReturn(Promise\resolve($okResponse));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction->send($requestWithAuthorization);
    }

    public function testAuthorizationHeaderIsForwardedWhenLocationContainsAuthentication()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $messageFactory->request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = $messageFactory->response(1.0, 301, null, array('Location' => 'http://user:pass@example.com/new'));
        $sender->expects($this->at(0))->method('send')->willReturn(Promise\resolve($redirectResponse));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = $messageFactory->response(1.0, 200, 'OK');
        $that = $this;
        $sender->expects($this->at(1))
            ->method('send')
            ->with($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertEquals('user:pass', $request->getUri()->getUserInfo());
                $that->assertFalse($request->hasHeader('Authorization'));
                return true;
            }))->willReturn(Promise\resolve($okResponse));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction->send($request);
    }

    public function testSomeRequestHeadersShouldBeRemovedWhenRedirecting()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $customHeaders = array(
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Length' => '111',
        );

        $requestWithCustomHeaders = $messageFactory->request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithCustomHeaders
        $redirectResponse = $messageFactory->response(1.0, 301, null, array('Location' => 'http://example.com/new'));
        $sender->expects($this->at(0))->method('send')->willReturn(Promise\resolve($redirectResponse));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithCustomHeaders
        $okResponse = $messageFactory->response(1.0, 200, 'OK');
        $that = $this;
        $sender->expects($this->at(1))
            ->method('send')
            ->with($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertFalse($request->hasHeader('Content-Type'));
                $that->assertFalse($request->hasHeader('Content-Length'));
                return true;
            }))->willReturn(Promise\resolve($okResponse));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction->send($requestWithCustomHeaders);
    }

    public function testCancelTransactionWillCancelRequest()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $messageFactory->request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $pending = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->once())->method('send')->willReturn($pending);

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelTimeoutTimer()
    {
        $messageFactory = new MessageFactory();

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = $messageFactory->request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $pending = new \React\Promise\Promise(function () { }, function () { throw new \RuntimeException(); });

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->once())->method('send')->willReturn($pending);

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelRedirectedRequest()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $messageFactory->request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $redirectResponse = $messageFactory->response(1.0, 301, null, array('Location' => 'http://example.com/new'));
        $sender->expects($this->at(0))->method('send')->willReturn(Promise\resolve($redirectResponse));

        $pending = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->at(1))->method('send')->willReturn($pending);

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelRedirectedRequestAgain()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $messageFactory->request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $first = new Deferred();
        $sender->expects($this->at(0))->method('send')->willReturn($first->promise());

        $second = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->at(1))->method('send')->willReturn($second);

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $promise = $transaction->send($request);

        // mock sender to resolve promise with the given $redirectResponse in
        $first->resolve($messageFactory->response(1.0, 301, null, array('Location' => 'http://example.com/new')));

        $promise->cancel();
    }

    public function testCancelTransactionWillCloseBufferingStream()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $messageFactory->request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $body = new ThroughStream();
        $body->on('close', $this->expectCallableOnce());

        // mock sender to resolve promise with the given $redirectResponse in
        $redirectResponse = $messageFactory->response(1.0, 301, null, array('Location' => 'http://example.com/new'), $body);
        $sender->expects($this->once())->method('send')->willReturn(Promise\resolve($redirectResponse));

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCloseBufferingStreamAgain()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $messageFactory->request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $first = new Deferred();
        $sender->expects($this->once())->method('send')->willReturn($first->promise());

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $promise = $transaction->send($request);

        $body = new ThroughStream();
        $body->on('close', $this->expectCallableOnce());

        // mock sender to resolve promise with the given $redirectResponse in
        $first->resolve($messageFactory->response(1.0, 301, null, array('Location' => 'http://example.com/new'), $body));
        $promise->cancel();
    }

    public function testCancelTransactionShouldCancelSendingPromise()
    {
        $messageFactory = new MessageFactory();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $messageFactory->request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $redirectResponse = $messageFactory->response(1.0, 301, null, array('Location' => 'http://example.com/new'));
        $sender->expects($this->at(0))->method('send')->willReturn(Promise\resolve($redirectResponse));

        $pending = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->at(1))->method('send')->willReturn($pending);

        $transaction = new Transaction($sender, $messageFactory, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    /**
     * @return MockObject
     */
    private function makeSenderMock()
    {
        return $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
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

    protected function createCallableMock()
    {
        return $this->getMockBuilder('stdClass')->setMethods(array('__invoke'))->getMock();
    }
}
