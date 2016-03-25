<?php

use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\Response;
use Clue\React\Buzz\Message\ResponseException;
use React\Promise;
use Clue\React\Block;

class TransactionTest extends TestCase
{
    public function testReceivingErrorResponseWillRejectWithResponseException()
    {
        $request = $this->getMockBuilder('Clue\React\Buzz\Message\Request')->disableOriginalConstructor()->getMock();
        $response = new Response('HTTP/1.0', 404, 'File not found');

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($request, $sender);
        $promise = $transaction->send();

        try {
            Block\await($promise, $this->getMock('React\EventLoop\LoopInterface'));
            $this->fail();
        } catch (ResponseException $exception) {
            $this->assertEquals(404, $exception->getCode());
            $this->assertSame($response, $exception->getResponse());
        }
    }
}
