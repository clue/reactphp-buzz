<?php

use Clue\React\Buzz\Io\Sender;
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

    public function testSenderRejection()
    {
        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector->expects($this->once())->method('create')->willReturn(Promise\reject(new RuntimeException('Rejected')));

        $sender = Sender::createFromLoopConnectors($this->loop, $connector);

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request, $this->getMock('Clue\React\Buzz\Message\MessageFactory'));

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
        $httpClientArguments = array();
        $ref = new \ReflectionClass('React\HttpClient\Client');
        $num = $ref->getConstructor()->getNumberOfRequiredParameters();

        if ($num === 1) {
            // react/http 0.5
            $httpClientArguments[] = $this->getMock('React\EventLoop\LoopInterface');
        } else {
            if ($num == 3) {
                // only for react/http 0.3
                $httpClientArguments[] = $this->getMock('React\EventLoop\LoopInterface');
            }
            $httpClientArguments[] = $this->getMock('React\SocketClient\ConnectorInterface');
            $httpClientArguments[] = $this->getMock('React\SocketClient\ConnectorInterface');
        }

        $http = $this->getMock(
            'React\HttpClient\Client',
            array(
                'request',
            ),
            $httpClientArguments
        );

        $requestArguments = array();
        if ($num === 1) {
            // react/http 0.5
            $requestArguments[] = $this->getMock('React\Socket\ConnectorInterface');
        } else {
            // react/http 0.4/0.3
            $ref = new \ReflectionClass('React\HttpClient\Request');
            $num = $ref->getConstructor()->getNumberOfRequiredParameters();

            if ($num === 3) {
                $requestArguments[] = $this->getMock('React\EventLoop\LoopInterface');
            }
            $requestArguments[] = $this->getMock('React\SocketClient\ConnectorInterface');
        }
        $requestArguments[] = new RequestData($method, $uri, $headers, $protocolVersion);

        $request = $this->getMock(
            'React\HttpClient\Request',
            array(),
            $requestArguments
        );
        $http->expects($this->once())->method('request')->with($method, $uri, $headers, $protocolVersion)->willReturn($request);

        $sender = new Sender($http);
        $sender->send($Request, $this->getMock('Clue\React\Buzz\Message\MessageFactory'));
    }
}
