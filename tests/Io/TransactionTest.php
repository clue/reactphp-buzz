<?php

use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\Response;

class TransactionTest extends TestCase
{
    public function testReceivingErrorResponseWillRejectWithResponseException()
    {
        $request = $this->getMockBuilder('Clue\React\Buzz\Message\Request')->disableOriginalConstructor()->getMock();
        $response = new Response('HTTP/1.0', 404, 'File not found');

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->will($this->returnValue($this->createPromiseResolved($response)));

        $transaction = new Transaction($request, $sender);
        $promise = $transaction->send();

        $that = $this;
        $this->expectPromiseReject($promise)->then(null, function ($exception) use ($that, $response) {
            $that->assertInstanceOf('Clue\React\Buzz\Message\ResponseException', $exception);
            $that->assertEquals(404, $exception->getCode());
            $that->assertSame($response, $exception->getResponse());
        });
    }
}
