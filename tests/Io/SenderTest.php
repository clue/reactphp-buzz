<?php

namespace Clue\Tests\React\Buzz\Io;

use Clue\React\Block;
use Clue\React\Buzz\Io\Sender;
use Clue\React\Buzz\Message\ReadableBodyStream;
use PHPUnit\Framework\TestCase;
use React\HttpClient\Client as HttpClient;
use React\HttpClient\RequestData;
use React\Promise;
use React\Stream\ThroughStream;
use RingCentral\Psr7\Request;

class SenderTest extends TestCase
{
    private $loop;

    public function setUp()
    {
        $this->loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
    }

    public function testCreateFromLoop()
    {
        $sender = Sender::createFromLoop($this->loop, null, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSenderRejectsInvalidUri()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->never())->method('connect');

        $sender = new Sender(new HttpClient($this->loop, $connector), $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('GET', 'www.google.com');

        $promise = $sender->send($request);

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSenderConnectorRejection()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn(Promise\reject(new \RuntimeException('Rejected')));

        $sender = new Sender(new HttpClient($this->loop, $connector), $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request);

        Block\await($promise, $this->loop);
    }

    public function testSendPostWillAutomaticallySendContentLengthHeader()
    {
        $client = $this->getMockBuilder('React\HttpClient\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'POST',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Content-Length' => '5'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\HttpClient\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('POST', 'http://www.google.com/', array(), 'hello');
        $sender->send($request);
    }

    public function testSendPostWillAutomaticallySendContentLengthZeroHeaderForEmptyRequestBody()
    {
        $client = $this->getMockBuilder('React\HttpClient\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'POST',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Content-Length' => '0'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\HttpClient\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('POST', 'http://www.google.com/', array(), '');
        $sender->send($request);
    }

    public function testSendPostStreamWillAutomaticallySendTransferEncodingChunked()
    {
        $client = $this->getMockBuilder('React\HttpClient\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'POST',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Transfer-Encoding' => 'chunked'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\HttpClient\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array(), new ReadableBodyStream($stream));
        $sender->send($request);
    }

    public function testSendPostStreamWithExplicitContentLengthWillSendHeaderAsIs()
    {
        $client = $this->getMockBuilder('React\HttpClient\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'POST',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Content-Length' => '100'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\HttpClient\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array('Content-Length' => '100'), new ReadableBodyStream($stream));
        $sender->send($request);
    }

    public function testSendGetWillNotPassContentLengthHeaderForEmptyRequestBody()
    {
        $client = $this->getMockBuilder('React\HttpClient\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'GET',
            'http://www.google.com/',
            array('Host' => 'www.google.com'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\HttpClient\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('GET', 'http://www.google.com/');
        $sender->send($request);
    }

    public function testSendCustomMethodWillNotPassContentLengthHeaderForEmptyRequestBody()
    {
        $client = $this->getMockBuilder('React\HttpClient\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'CUSTOM',
            'http://www.google.com/',
            array('Host' => 'www.google.com'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\HttpClient\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('CUSTOM', 'http://www.google.com/');
        $sender->send($request);
    }

    public function testSendCustomMethodWithExplicitContentLengthZeroWillBePassedAsIs()
    {
        $client = $this->getMockBuilder('React\HttpClient\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'CUSTOM',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Content-Length' => '0'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\HttpClient\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('CUSTOM', 'http://www.google.com/', array('Content-Length' => '0'));
        $sender->send($request);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCancelRequestWillCancelConnector()
    {
        $promise = new \React\Promise\Promise(function () { }, function () {
            throw new \RuntimeException();
        });

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn($promise);

        $sender = new Sender(new HttpClient($this->loop, $connector), $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request);
        $promise->cancel();

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCancelRequestWillCloseConnection()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('close');

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn(Promise\resolve($connection));

        $sender = new Sender(new HttpClient($this->loop, $connector), $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request);
        $promise->cancel();

        Block\await($promise, $this->loop);
    }

    public function provideRequestProtocolVersion()
    {
        return array(
            array(
                new Request('GET', 'http://www.google.com/'),
                'GET',
                'http://www.google.com/',
                array(
                    'Host' => 'www.google.com',
                ),
                '1.1',
            ),
            array(
                new Request('GET', 'http://www.google.com/', array(), '', '1.0'),
                'GET',
                'http://www.google.com/',
                array(
                    'Host' => 'www.google.com',
                ),
                '1.0',
            ),
        );
    }

    /**
     * @dataProvider provideRequestProtocolVersion
     */
    public function testRequestProtocolVersion(Request $Request, $method, $uri, $headers, $protocolVersion)
    {
        $http = $this->getMockBuilder('React\HttpClient\Client')
                    ->setMethods(array(
                        'request',
                    ))
                    ->setConstructorArgs(array(
                        $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock(),
                    ))->getMock();

        $request = $this->getMockBuilder('React\HttpClient\Request')
                        ->setMethods(array())
                        ->setConstructorArgs(array(
                            $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock(),
                            new RequestData($method, $uri, $headers, $protocolVersion),
                        ))->getMock();

        $http->expects($this->once())->method('request')->with($method, $uri, $headers, $protocolVersion)->willReturn($request);

        $sender = new Sender($http, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());
        $sender->send($Request);
    }
}
