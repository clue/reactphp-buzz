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
        $this->loop = $this->getMock('React\EventLoop\LoopInterface');
    }

    public function testCreateFromLoop()
    {
        $sender = Sender::createFromLoop($this->loop);

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    public function testCreateFromLoopConnectors()
    {
        $connector = $this->getMock('React\SocketClient\ConnectorInterface');

        $sender = Sender::createFromLoopConnectors($this->loop, $connector);

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    public function testCreateFromLoopUnix()
    {
        $sender = Sender::createFromLoopUnix($this->loop, 'unix:///run/daemon.sock');

        $this->assertInstanceOf('Clue\React\Buzz\Io\Sender', $sender);
    }

    public function testSenderConnectorRejection()
    {
        $connector = $this->getMock('React\Socket\ConnectorInterface');
        $connector->expects($this->once())->method('connect')->willReturn(Promise\reject(new RuntimeException('Rejected')));

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request, $this->getMock('Clue\React\Buzz\Message\MessageFactory'));

        $this->setExpectedException('RuntimeException');
        Block\await($promise, $this->loop);
    }

    public function testSenderLegacyConnectorRejection()
    {
        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector->expects($this->once())->method('connect')->willReturn(Promise\reject(new RuntimeException('Rejected')));

        $sender = Sender::createFromLoopConnectors($this->loop, $connector);

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request, $this->getMock('Clue\React\Buzz\Message\MessageFactory'));

        $this->setExpectedException('RuntimeException');
        Block\await($promise, $this->loop);
    }

    public function testCancelRequestWillCancelConnector()
    {
        $promise = new \React\Promise\Promise(function () { }, function () {
            throw new \RuntimeException();
        });

        $connector = $this->getMock('React\Socket\ConnectorInterface');
        $connector->expects($this->once())->method('connect')->willReturn($promise);

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request, $this->getMock('Clue\React\Buzz\Message\MessageFactory'));
        $promise->cancel();

        $this->setExpectedException('RuntimeException');
        Block\await($promise, $this->loop);
    }

    public function testCancelRequestWillCloseConnection()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('close');

        $connector = $this->getMock('React\Socket\ConnectorInterface');
        $connector->expects($this->once())->method('connect')->willReturn(Promise\resolve($connection));

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request, $this->getMock('Clue\React\Buzz\Message\MessageFactory'));
        $promise->cancel();

        $this->setExpectedException('RuntimeException');
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
        $http = $this->getMock(
            'React\HttpClient\Client',
            array(
                'request',
            ),
            array(
                $this->getMock('React\EventLoop\LoopInterface')
            )
        );

        $request = $this->getMock(
            'React\HttpClient\Request',
            array(),
            array(
                $this->getMock('React\Socket\ConnectorInterface'),
                new RequestData($method, $uri, $headers, $protocolVersion)
            )
        );

        $http->expects($this->once())->method('request')->with($method, $uri, $headers, $protocolVersion)->willReturn($request);

        $sender = new Sender($http);
        $sender->send($Request, $this->getMock('Clue\React\Buzz\Message\MessageFactory'));
    }
}
