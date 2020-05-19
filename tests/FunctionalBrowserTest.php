<?php

namespace Clue\Tests\React\Buzz;

use Clue\React\Block;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\ResponseException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\StreamingServer;
use React\Promise\Stream;
use React\Socket\Connector;
use React\Stream\ThroughStream;
use RingCentral\Psr7\Request;

class FunctionalBrowserTest extends TestCase
{
    private $loop;
    private $browser;

    /** base url to the httpbin service  **/
    private $base = 'http://httpbin.org/';

    public function setUp()
    {
        $this->loop = Factory::create();
        $this->browser = new Browser($this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testSimpleRequest()
    {
        Block\await($this->browser->get($this->base . 'get'), $this->loop);
    }

    /**
     * @expectedException RuntimeException
     * @group online
     */
    public function testCancelGetRequestWillRejectRequest()
    {
        $promise = $this->browser->get($this->base . 'get');
        $promise->cancel();

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException RuntimeException
     * @group online
     */
    public function testCancelSendWithPromiseFollowerWillRejectRequest()
    {
        $promise = $this->browser->send(new Request('GET', $this->base . 'get'))->then(function () {
            var_dump('noop');
        });
        $promise->cancel();

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException RuntimeException
     * @group online
     */
    public function testRequestWithoutAuthenticationFails()
    {
        Block\await($this->browser->get($this->base . 'basic-auth/user/pass'), $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testRequestWithAuthenticationSucceeds()
    {
        $base = str_replace('://', '://user:pass@', $this->base);

        Block\await($this->browser->get($base . 'basic-auth/user/pass'), $this->loop);
    }

    /**
     * ```bash
     * $ curl -vL "http://httpbin.org/redirect-to?url=http://user:pass@httpbin.org/basic-auth/user/pass"
     * ```
     *
     * @group online
     * @doesNotPerformAssertions
     */
    public function testRedirectToPageWithAuthenticationSucceeds()
    {
        $target = str_replace('://', '://user:pass@', $this->base) . '/basic-auth/user/pass';

        Block\await($this->browser->get($this->base . 'redirect-to?url=' . urlencode($target)), $this->loop);
    }

    /**
     * ```bash
     * $ curl -vL "http://unknown:invalid@httpbin.org/redirect-to?url=http://user:pass@httpbin.org/basic-auth/user/pass"
     * ```
     *
     * @group online
     * @doesNotPerformAssertions
     */
    public function testRedirectFromPageWithInvalidAuthToPageWithCorrectAuthenticationSucceeds()
    {
        $base = str_replace('://', '://unknown:invalid@', $this->base);
        $target = str_replace('://', '://user:pass@', $this->base) . '/basic-auth/user/pass';

        Block\await($this->browser->get($base . 'redirect-to?url=' . urlencode($target)), $this->loop);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Request cancelled
     * @group online
     */
    public function testCancelRedirectedRequestShouldReject()
    {
        $promise = $this->browser->get($this->base . 'redirect-to?url=delay%2F10');

        $this->loop->addTimer(0.1, function () use ($promise) {
            $promise->cancel();
        });

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Request timed out after 0.1 seconds
     * @group online
     */
    public function testTimeoutDelayedResponseShouldReject()
    {
        $promise = $this->browser->withOptions(array('timeout' => 0.1))->get($this->base . 'delay/10');

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Request timed out after 0.1 seconds
     * @group online
     */
    public function testTimeoutDelayedResponseAfterStreamingRequestShouldReject()
    {
        $stream = new ThroughStream();
        $promise = $this->browser->withOptions(array('timeout' => 0.1))->post($this->base . 'delay/10', array(), $stream);
        $stream->end();

        Block\await($promise, $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testTimeoutNegativeShouldResolveSuccessfully()
    {
        Block\await($this->browser->withOptions(array('timeout' => -1))->get($this->base . 'get'), $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testRedirectRequestRelative()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=get'), $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testRedirectRequestAbsolute()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=' . urlencode($this->base . 'get')), $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testNotFollowingRedirectsResolvesWithRedirectResult()
    {
        $browser = $this->browser->withOptions(array('followRedirects' => false));

        Block\await($browser->get($this->base . 'redirect/3'), $this->loop);
    }

    /**
     * @group online
     * @expectedException RuntimeException
     */
    public function testRejectingRedirectsRejects()
    {
        $browser = $this->browser->withOptions(array('maxRedirects' => 0));

        Block\await($browser->get($this->base . 'redirect/3'), $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testResponseStatus300WithoutLocationShouldResolveWithoutFollowingRedirect()
    {
        Block\await($this->browser->get($this->base . 'status/300'), $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testCanAccessHttps()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        Block\await($this->browser->get('https://www.google.com/'), $this->loop);
    }

    /**
     * @group online
     * @expectedException RuntimeException
     */
    public function testVerifyPeerEnabledForBadSslRejects()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $connector = new Connector($this->loop, array(
            'tls' => array(
                'verify_peer' => true
            )
        ));

        $browser = new Browser($this->loop, $connector);

        Block\await($browser->get('https://self-signed.badssl.com/'), $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testVerifyPeerDisabledForBadSslResolves()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $connector = new Connector($this->loop, array(
            'tls' => array(
                'verify_peer' => false
            )
        ));

        $browser = new Browser($this->loop, $connector);

        Block\await($browser->get('https://self-signed.badssl.com/'), $this->loop);
    }

    /**
     * @group online
     * @expectedException RuntimeException
     */
    public function testInvalidPort()
    {
        Block\await($this->browser->get('http://www.google.com:443/'), $this->loop);
    }

    /** @group online */
    public function testErrorStatusCodeRejectsWithResponseException()
    {
        try {
            Block\await($this->browser->get($this->base . 'status/404'), $this->loop);
            $this->fail();
        } catch (ResponseException $e) {
            $this->assertEquals(404, $e->getCode());

            $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $e->getResponse());
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
        }
    }

    /** @group online */
    public function testPostString()
    {
        $response = Block\await($this->browser->post($this->base . 'post', array(), 'hello world'), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
    }

    public function testReceiveStreamUntilConnectionsEndsForHttp10()
    {
        $loop = $this->loop;
        $server = new StreamingServer(function (ServerRequestInterface $request) use ($loop) {
            $stream = new ThroughStream();

            $loop->futureTick(function () use ($stream) {
                $stream->end('hello');
            });

            return new Response(
                200,
                array(),
                $stream
            );
        });

        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->withProtocolVersion('1.0')->get($this->base . 'get', array()), $this->loop);

        $this->assertEquals('1.0', $response->getProtocolVersion());
        $this->assertFalse($response->hasHeader('Transfer-Encoding'));
        $this->assertEquals('hello', (string)$response->getBody());

        $socket->close();
    }

    public function testReceiveStreamChunkedForHttp11()
    {
        $loop = $this->loop;
        $server = new StreamingServer(function (ServerRequestInterface $request) use ($loop) {
            $stream = new ThroughStream();

            $loop->futureTick(function () use ($stream) {
                $stream->end('hello');
            });

            return new Response(
                200,
                array(),
                $stream
            );
        });

        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->send(new Request('GET', $this->base . 'get', array(), null, '1.1')), $this->loop);

        $this->assertEquals('1.1', $response->getProtocolVersion());

        // underlying http-client automatically decodes and doesn't expose header
        // @link https://github.com/reactphp/http-client/pull/58
        // $this->assertEquals('chunked', $response->getHeaderLine('Transfer-Encoding'));
        $this->assertFalse($response->hasHeader('Transfer-Encoding'));

        $this->assertEquals('hello', (string)$response->getBody());

        $socket->close();
    }

    public function testReceiveStreamAndExplicitlyCloseConnectionEvenWhenServerKeepsConnectionOpen()
    {
        $closed = new \React\Promise\Deferred();
        $socket = new \React\Socket\Server(0, $this->loop);
        $socket->on('connection', function (\React\Socket\ConnectionInterface $connection) use ($closed) {
            $connection->on('data', function () use ($connection) {
                $connection->write("HTTP/1.1 200 OK\r\nContent-Length: 5\r\n\r\nhello");
            });
            $connection->on('close', function () use ($closed) {
                $closed->resolve(true);
            });
        });

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->get($this->base . 'get', array()), $this->loop);
        $this->assertEquals('hello', (string)$response->getBody());

        $ret = Block\await($closed->promise(), $this->loop, 0.1);
        $this->assertTrue($ret);

        $socket->close();
    }

    public function testPostStreamChunked()
    {
        // httpbin used to support `Transfer-Encoding: chunked` for requests,
        // but now rejects these, so let's start our own server instance
        $that = $this;
        $server = new StreamingServer(function (ServerRequestInterface $request) use ($that) {
            $that->assertFalse($request->hasHeader('Content-Length'));
            $that->assertNull($request->getBody()->getSize());

            return Stream\buffer($request->getBody())->then(function ($body) {
                return new Response(
                    200,
                    array(),
                    json_encode(array(
                        'data' => $body
                    ))
                );
            });
        });
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $stream = new ThroughStream();

        $this->loop->addTimer(0.001, function () use ($stream) {
            $stream->end('hello world');
        });

        $response = Block\await($this->browser->post($this->base . 'post', array(), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);

        $socket->close();
    }

    /** @group online */
    public function testPostStreamKnownLength()
    {
        $stream = new ThroughStream();

        $this->loop->addTimer(0.001, function () use ($stream) {
            $stream->end('hello world');
        });

        $response = Block\await($this->browser->post($this->base . 'post', array('Content-Length' => 11), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPostStreamWillStartSendingRequestEvenWhenBodyDoesNotEmitData()
    {
        $server = new StreamingServer(function (ServerRequestInterface $request) {
            return new Response(200);
        });
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $stream = new ThroughStream();
        Block\await($this->browser->post($this->base . 'post', array(), $stream), $this->loop);

        $socket->close();
    }

    /** @group online */
    public function testPostStreamClosed()
    {
        $stream = new ThroughStream();
        $stream->close();

        $response = Block\await($this->browser->post($this->base . 'post', array(), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('', $data['data']);
    }

    public function testSendsHttp11ByDefault()
    {
        $server = new StreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                $request->getProtocolVersion()
            );
        });
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->get($this->base), $this->loop);
        $this->assertEquals('1.1', (string)$response->getBody());

        $socket->close();
    }

    public function testSendsExplicitHttp10Request()
    {
        $server = new StreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                $request->getProtocolVersion()
            );
        });
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->withProtocolVersion('1.0')->get($this->base), $this->loop);
        $this->assertEquals('1.0', (string)$response->getBody());

        $socket->close();
    }
}
