<?php

use Clue\React\Buzz\Io\Sender;
use React\HttpClient\Client as HttpClient;
use React\HttpClient\RequestData;
use RingCentral\Psr7\Request;
use React\Promise;
use Clue\React\Block;

class SenderTest extends TestCase
{
    private $loop;

    public function setUp()
    {
        $this->loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
    }

    public function testCreateFromLoop()
    {
        $sender = Sender::createFromLoop($this->loop);

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSenderConnectorRejection()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn(Promise\reject(new RuntimeException('Rejected')));

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());

        Block\await($promise, $this->loop);
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

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());
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

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());
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

        $sender = new Sender($http);
        $sender->send($Request, $this->getMockBuilder('Clue\React\Buzz\Message\MessageFactory')->getMock());
    }
}
