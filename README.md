# clue/reactphp-buzz [![Build Status](https://travis-ci.org/clue/reactphp-buzz.svg?branch=master)](https://travis-ci.org/clue/reactphp-buzz)

Simple, async PSR-7 HTTP client for concurrently processing any number of HTTP requests,
built on top of [ReactPHP](https://reactphp.org/).

This library is heavily inspired by the great
[kriswallsmith/Buzz](https://github.com/kriswallsmith/Buzz)
project. However, instead of blocking on each request, it relies on
[ReactPHP's EventLoop](https://github.com/reactphp/event-loop) to process
multiple requests in parallel.
This allows you to interact with multiple HTTP servers
(fetch URLs, talk to RESTful APIs, follow redirects etc.)
at the same time.
Unlike the underlying [react/http-client](https://github.com/reactphp/http-client),
this project aims at providing a higher-level API that is easy to use
in order to process multiple HTTP requests concurrently without having to
mess with most of the low-level details.

* **Async execution of HTTP requests** -
  Send any number of HTTP requests to any number of HTTP servers in parallel and
  process their responses as soon as results come in.
  The Promise-based design provides a *sane* interface to working with out of bound responses.
* **Standard interfaces** -
  Allows easy integration with existing higher-level components by implementing
  [PSR-7 (http-message)](https://www.php-fig.org/psr/psr-7/) interfaces,
  ReactPHP's standard [promises](#promises) and [streaming interfaces](#streaming-response).
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](https://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  Builds on top of well-tested components and well-established concepts instead of reinventing the wheel.
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested in the *real world*.

**Table of contents**

* [Support us](#support-us)
* [Quickstart example](#quickstart-example)
* [Usage](#usage)
    * [Request methods](#request-methods)
    * [Promises](#promises)
    * [Cancellation](#cancellation)
    * [Timeouts](#timeouts)
    * [Authentication](#authentication)
    * [Redirects](#redirects)
    * [Blocking](#blocking)
    * [Streaming response](#streaming-response)
    * [Streaming request](#streaming-request)
    * [HTTP proxy](#http-proxy)
    * [SOCKS proxy](#socks-proxy)
    * [SSH proxy](#ssh-proxy)
    * [Unix domain sockets](#unix-domain-sockets)
* [API](#api)
    * [Browser](#browser)
        * [get()](#get)
        * [post()](#post)
        * [head()](#head)
        * [patch()](#patch)
        * [put()](#put)
        * [delete()](#delete)
        * [submit()](#submit)
        * [send()](#send)
        * [withOptions()](#withoptions)
        * [withBase()](#withbase)
        * [withoutBase()](#withoutbase)
        * [withProtocolVersion()](#withprotocolversion)
    * [ResponseInterface](#responseinterface)
    * [RequestInterface](#requestinterface)
    * [UriInterface](#uriinterface)
    * [ResponseException](#responseexception)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Support us

We invest a lot of time developing, maintaining and updating our awesome
open-source projects. You can help us sustain this high-quality of our work by
[becoming a sponsor on GitHub](https://github.com/sponsors/clue). Sponsors get
numerous benefits in return, see our [sponsoring page](https://github.com/sponsors/clue)
for details.

Let's take these projects to the next level together! 🚀

## Quickstart example

Once [installed](#install), you can use the following code to access a
HTTP webserver and send some simple HTTP GET requests:

```php
$loop = React\EventLoop\Factory::create();
$client = new Clue\React\Buzz\Browser($loop);

$client->get('http://www.google.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string)$response->getBody());
});

$loop->run();
```

See also the [examples](examples).

## Usage

### Request methods

<a id="methods"></a><!-- legacy fragment id -->

Most importantly, this project provides a [`Browser`](#browser) object that
offers several methods that resemble the HTTP protocol methods:

```php
$browser->get($url, array $headers = array());
$browser->head($url, array $headers = array());
$browser->post($url, array $headers = array(), string|ReadableStreamInterface $contents = '');
$browser->delete($url, array $headers = array(), string|ReadableStreamInterface $contents = '');
$browser->put($url, array $headers = array(), string|ReadableStreamInterface $contents = '');
$browser->patch($url, array $headers = array(), string|ReadableStreamInterface $contents = '');
```

Each of these methods requires a `$url` and some optional parameters to send an
HTTP request. Each of these method names matches the respective HTTP request
method, for example the [`get()`](#get) method sends an HTTP `GET` request.

You can optionally pass an associative array of additional `$headers` that will be
sent with this HTTP request. Additionally, each method will automatically add a
matching `Content-Length` request header if an outgoing request body is given and its
size is known and non-empty. For an empty request body, if will only include a
`Content-Length: 0` request header if the request method usually expects a request
body (only applies to `POST`, `PUT` and `PATCH` HTTP request methods).

If you're using a [streaming request body](#streaming-request), it will default
to using `Transfer-Encoding: chunked` unless you explicitly pass in a matching `Content-Length`
request header. See also [streaming request](#streaming-request) for more details.

By default, all of the above methods default to sending requests using the
HTTP/1.1 protocol version. If you want to explicitly use the legacy HTTP/1.0
protocol version, you can use the [`withProtocolVersion()`](#withprotocolversion)
method. If you want to use any other or even custom HTTP request method, you can
use the [`send()`](#send) method.

Each of the above methods supports async operation and either *fulfills* with a
[`ResponseInterface`](#responseinterface) or *rejects* with an `Exception`.
Please see the following chapter about [promises](#promises) for more details.

### Promises

Sending requests is async (non-blocking), so you can actually send multiple
requests in parallel.
The `Browser` will respond to each request with a [`ResponseInterface`](#responseinterface)
message, the order is not guaranteed.
Sending requests uses a [Promise](https://github.com/reactphp/promise)-based
interface that makes it easy to react to when an HTTP request is completed
(i.e. either successfully fulfilled or rejected with an error):

```php
$browser->get($url)->then(
    function (Psr\Http\Message\ResponseInterface $response) {
        var_dump('Response received', $response);
    },
    function (Exception $error) {
        var_dump('There was an error', $error->getMessage());
    }
);
```

If this looks strange to you, you can also use the more traditional [blocking API](#blocking).

Keep in mind that resolving the Promise with the full response message means the
whole response body has to be kept in memory.
This is easy to get started and works reasonably well for smaller responses
(such as common HTML pages or RESTful or JSON API requests).

You may also want to look into the [streaming API](#streaming-response):

* If you're dealing with lots of concurrent requests (100+) or
* If you want to process individual data chunks as they happen (without having to wait for the full response body) or
* If you're expecting a big response body size (1 MiB or more, for example when downloading binary files) or
* If you're unsure about the response body size (better be safe than sorry when accessing arbitrary remote HTTP endpoints and the response body size is unknown in advance).

### Cancellation

The returned Promise is implemented in such a way that it can be cancelled
when it is still pending.
Cancelling a pending promise will reject its value with an Exception and
clean up any underlying resources.

```php
$promise = $browser->get($url);

$loop->addTimer(2.0, function () use ($promise) {
    $promise->cancel();
});
```

### Timeouts

This library uses a very efficient HTTP implementation, so most HTTP requests
should usually be completed in mere milliseconds. However, when sending HTTP
requests over an unreliable network (the internet), there are a number of things
that can go wrong and may cause the request to fail after a time. As such, this
library respects PHP's `default_socket_timeout` setting (default 60s) as a timeout
for sending the outgoing HTTP request and waiting for a successful response and
will otherwise cancel the pending request and reject its value with an Exception.

Note that this timeout value covers creating the underlying transport connection,
sending the HTTP request, receiving the HTTP response headers and its full
response body and following any eventual [redirects](#redirects). See also
[redirects](#redirects) below to configure the number of redirects to follow (or
disable following redirects altogether) and also [streaming](#streaming-response)
below to not take receiving large response bodies into account for this timeout.

You can use the [`timeout` option](#withoptions) to pass a custom timeout value
in seconds like this:

```php
$browser = $browser->withOptions(array(
    'timeout' => 10.0
));

$browser->get($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
    // response received within 10 seconds maximum
    var_dump($response->getHeaders());
});
```

Similarly, you can use a negative timeout value to not apply a timeout at all
or use a `null` value to restore the default handling.
See also [`withOptions()`](#withoptions) for more details.

If you're using a [streaming response body](#streaming-response), the time it
takes to receive the response body stream will not be included in the timeout.
This allows you to keep this incoming stream open for a longer time, such as
when downloading a very large stream or when streaming data over a long-lived
connection.

If you're using a [streaming request body](#streaming-request), the time it
takes to send the request body stream will not be included in the timeout. This
allows you to keep this outgoing stream open for a longer time, such as when
uploading a very large stream.

Note that this timeout handling applies to the higher-level HTTP layer. Lower
layers such as socket and DNS may also apply (different) timeout values. In
particular, the underlying socket connection uses the same `default_socket_timeout`
setting to establish the underlying transport connection. To control this
connection timeout behavior, you can [inject a custom `Connector`](#browser)
like this:

```php
$browser = new Clue\React\Buzz\Browser(
    $loop,
    new React\Socket\Connector(
        $loop,
        array(
            'timeout' => 5
        )
    )
);
```

### Authentication

This library supports [HTTP Basic Authentication](https://en.wikipedia.org/wiki/Basic_access_authentication)
using the `Authorization: Basic …` request header or allows you to set an explicit
`Authorization` request header.

By default, this library does not include an outgoing `Authorization` request
header. If the server requires authentication, if may return a `401` (Unauthorized)
status code which will reject the request by default (see also
[`obeySuccessCode` option](#withoptions) below).

In order to pass authentication details, you can simple pass the username and
password as part of the request URL like this:

```php
$promise = $browser->get('https://user:pass@example.com/api');
```

Note that special characters in the authentication details have to be
percent-encoded, see also [`rawurlencode()`](https://www.php.net/manual/en/function.rawurlencode.php).
This example will automatically pass the base64-encoded authentication details
using the outgoing `Authorization: Basic …` request header. If the HTTP endpoint
you're talking to requires any other authentication scheme, you can also pass
this header explicitly. This is common when using (RESTful) HTTP APIs that use
OAuth access tokens or JSON Web Tokens (JWT):

```php
$token = 'abc123';

$promise = $browser->get(
    'https://example.com/api',
    array(
        'Authorization' => 'Bearer ' . $token
    )
);
```

When following redirects, the `Authorization` request header will never be sent
to any remote hosts by default. When following a redirect where the `Location`
response header contains authentication details, these details will be sent for
following requests. See also [redirects](#redirects) below.

### Redirects

By default, this library follows any redirects and obeys `3xx` (Redirection)
status codes using the `Location` response header from the remote server.
The promise will be fulfilled with the last response from the chain of redirects.

```php
$browser->get($url, $headers)->then(function (Psr\Http\Message\ResponseInterface $response) {
    // the final response will end up here
    var_dump($response->getHeaders());
});
```

Any redirected requests will follow the semantics of the original request and
will include the same request headers as the original request except for those
listed below.
If the original request contained a request body, this request body will never
be passed to the redirected request. Accordingly, each redirected request will
remove any `Content-Length` and `Content-Type` request headers.

If the original request used HTTP authentication with an `Authorization` request
header, this request header will only be passed as part of the redirected
request if the redirected URL is using the same host. In other words, the
`Authorizaton` request header will not be forwarded to other foreign hosts due to
possible privacy/security concerns. When following a redirect where the `Location`
response header contains authentication details, these details will be sent for
following requests.

You can use the [`maxRedirects` option](#withoptions) to control the maximum
number of redirects to follow or the [`followRedirects` option](#withoptions)
to return any redirect responses as-is and apply custom redirection logic
like this:

```php
$browser = $browser->withOptions(array(
    'followRedirects' => false
));

$browser->get($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
    // any redirects will now end up here
    var_dump($response->getHeaders());
});
```

See also [`withOptions()`](#withoptions) for more details.

### Blocking

As stated above, this library provides you a powerful, async API by default.

If, however, you want to integrate this into your traditional, blocking environment,
you should look into also using [clue/reactphp-block](https://github.com/clue/reactphp-block).

The resulting blocking code could look something like this:

```php
use Clue\React\Block;

$loop = React\EventLoop\Factory::create();
$browser = new Clue\React\Buzz\Browser($loop);

$promise = $browser->get('http://example.com/');

try {
    $response = Block\await($promise, $loop);
    // response successfully received
} catch (Exception $e) {
    // an error occured while performing the request
}
```

Similarly, you can also process multiple requests concurrently and await an array of `Response` objects:

```php
$promises = array(
    $browser->get('http://example.com/'),
    $browser->get('http://www.example.org/'),
);

$responses = Block\awaitAll($promises, $loop);
```

Please refer to [clue/reactphp-block](https://github.com/clue/reactphp-block#readme) for more details.

Keep in mind the above remark about buffering the whole response message in memory.
As an alternative, you may also see the following chapter for the
[streaming API](#streaming-response).

### Streaming response

<a id="streaming"></a><!-- legacy fragment id -->

All of the above examples assume you want to store the whole response body in memory.
This is easy to get started and works reasonably well for smaller responses.

However, there are several situations where it's usually a better idea to use a
streaming approach, where only small chunks have to be kept in memory:

* If you're dealing with lots of concurrent requests (100+) or
* If you want to process individual data chunks as they happen (without having to wait for the full response body) or
* If you're expecting a big response body size (1 MiB or more, for example when downloading binary files) or
* If you're unsure about the response body size (better be safe than sorry when accessing arbitrary remote HTTP endpoints and the response body size is unknown in advance). 

The streaming API uses the same HTTP message API, but does not buffer the response
message body in memory.
It only processes the response body in small chunks as data is received and
forwards this data through [React's Stream API](https://github.com/reactphp/stream).
This works for (any number of) responses of arbitrary sizes.

This resolves with a normal [`ResponseInterface`](#responseinterface), which
can be used to access the response message parameters as usual.
You can access the message body as usual, however it now also
implements ReactPHP's [`ReadableStreamInterface`](https://github.com/reactphp/stream#readablestreaminterface)
as well as parts of the PSR-7's [`StreamInterface`](https://www.php-fig.org/psr/psr-7/#3-4-psr-http-message-streaminterface).

```php
// turn on streaming responses (does no longer buffer response body)
$streamingBrowser = $browser->withOptions(array('streaming' => true));

// issue a normal GET request
$streamingBrowser->get($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
    $body = $response->getBody();
    assert($body instanceof React\Stream\ReadableStreamInterface);

    $body->on('data', function ($chunk) {
        echo $chunk;
    });

    $body->on('error', function (Exception $error) {
        echo 'Error: ' . $error->getMessage() . PHP_EOL;
    });

    $body->on('close', function () {
        echo '[DONE]' . PHP_EOL;
    });
});
```

See also the [stream download example](examples/91-benchmark-download.php) and
the [stream forwarding example](examples/21-stream-forwarding.php).

You can invoke the following methods on the message body:

```php
$body->on($event, $callback);
$body->eof();
$body->isReadable();
$body->pipe(React\Stream\WritableStreamInterface $dest, array $options = array());
$body->close();
$body->pause();
$body->resume();
```

Because the message body is in a streaming state, invoking the following methods
doesn't make much sense:

```php
$body->__toString(); // ''
$body->detach(); // throws BadMethodCallException
$body->getSize(); // null
$body->tell(); // throws BadMethodCallException
$body->isSeekable(); // false
$body->seek(); // throws BadMethodCallException
$body->rewind(); // throws BadMethodCallException
$body->isWritable(); // false
$body->write(); // throws BadMethodCallException
$body->read(); // throws BadMethodCallException
$body->getContents(); // throws BadMethodCallException
```

Note how [timeouts](#timeouts) apply slightly differently when using streaming.
In streaming mode, the timeout value covers creating the underlying transport
connection, sending the HTTP request, receiving the HTTP response headers and
following any eventual [redirects](#redirects). In particular, the timeout value
does not take receiving (possibly large) response bodies into account.

If you want to integrate the streaming response into a higher level API, then
working with Promise objects that resolve with Stream objects is often inconvenient.
Consider looking into also using [react/promise-stream](https://github.com/reactphp/promise-stream).
The resulting streaming code could look something like this:

```php
use React\Promise\Stream;

function download(Browser $browser, string $url): React\Stream\ReadableStreamInterface {
    return Stream\unwrapReadable(
        $browser->withOptions(['streaming' => true])->get($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
            return $response->getBody();
        })
    );
}

$stream = download($browser, $url);
$stream->on('data', function ($data) {
    echo $data;
});
```

### Streaming request

Besides streaming the response body, you can also stream the request body.
This can be useful if you want to send big POST requests (uploading files etc.)
or process many outgoing streams at once.
Instead of passing the body as a string, you can simply pass an instance
implementing ReactPHP's [`ReadableStreamInterface`](https://github.com/reactphp/stream#readablestreaminterface)
to the [request methods](#request-methods) like this:

```php
$browser->post($url, array(), $stream)->then(function (Psr\Http\Message\ResponseInterface $response) {
    echo 'Successfully sent.';
});
```

If you're using a streaming request body (`React\Stream\ReadableStreamInterface`), it will
default to using `Transfer-Encoding: chunked` or you have to explicitly pass in a
matching `Content-Length` request header like so:

```php
$body = new React\Stream\ThroughStream();
$loop->addTimer(1.0, function () use ($body) {
    $body->end("hello world");
});

$browser->post($url, array('Content-Length' => '11'), $body);
```

If the streaming request body emits an `error` event or is explicitly closed
without emitting a successful `end` event first, the request will automatically
be closed and rejected.

### HTTP proxy

You can also establish your outgoing connections through an HTTP CONNECT proxy server
by adding a dependency to [clue/reactphp-http-proxy](https://github.com/clue/reactphp-http-proxy).

HTTP CONNECT proxy servers (also commonly known as "HTTPS proxy" or "SSL proxy")
are commonly used to tunnel HTTPS traffic through an intermediary ("proxy"), to
conceal the origin address (anonymity) or to circumvent address blocking
(geoblocking). While many (public) HTTP CONNECT proxy servers often limit this
to HTTPS port`443` only, this can technically be used to tunnel any TCP/IP-based
protocol, such as plain HTTP and TLS-encrypted HTTPS.

```php
$proxy = new Clue\React\HttpProxy\ProxyConnector(
    'http://127.0.0.1:8080',
    new React\Socket\Connector($loop)
);

$connector = new React\Socket\Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new Clue\React\Buzz\Browser($loop, $connector);
```

See also the [HTTP CONNECT proxy example](examples/11-http-proxy.php).

### SOCKS proxy

You can also establish your outgoing connections through a SOCKS proxy server
by adding a dependency to [clue/reactphp-socks](https://github.com/clue/reactphp-socks).

The SOCKS proxy protocol family (SOCKS5, SOCKS4 and SOCKS4a) is commonly used to
tunnel HTTP(S) traffic through an intermediary ("proxy"), to conceal the origin
address (anonymity) or to circumvent address blocking (geoblocking). While many
(public) SOCKS proxy servers often limit this to HTTP(S) port `80` and `443`
only, this can technically be used to tunnel any TCP/IP-based protocol.

```php
$proxy = new Clue\React\Socks\Client(
    'socks://127.0.0.1:1080',
    new React\Socket\Connector($loop)
);

$connector = new React\Socket\Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new Clue\React\Buzz\Browser($loop, $connector);
```

See also the [SOCKS proxy example](examples/12-socks-proxy.php).

### SSH proxy

You can also establish your outgoing connections through an SSH server
by adding a dependency to [clue/reactphp-ssh-proxy](https://github.com/clue/reactphp-ssh-proxy).

[Secure Shell (SSH)](https://en.wikipedia.org/wiki/Secure_Shell) is a secure
network protocol that is most commonly used to access a login shell on a remote
server. Its architecture allows it to use multiple secure channels over a single
connection. Among others, this can also be used to create an "SSH tunnel", which
is commonly used to tunnel HTTP(S) traffic through an intermediary ("proxy"), to
conceal the origin address (anonymity) or to circumvent address blocking
(geoblocking). This can be used to tunnel any TCP/IP-based protocol (HTTP, SMTP,
IMAP etc.), allows you to access local services that are otherwise not accessible
from the outside (database behind firewall) and as such can also be used for
plain HTTP and TLS-encrypted HTTPS.

```php
$proxy = new Clue\React\SshProxy\SshSocksConnector('me@localhost:22', $loop);

$connector = new React\Socket\Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new Clue\React\Buzz\Browser($loop, $connector);
```

See also the [SSH proxy example](examples/13-ssh-proxy.php).

### Unix domain sockets

By default, this library supports transport over plaintext TCP/IP and secure
TLS connections for the `http://` and `https://` URL schemes respectively.
This library also supports Unix domain sockets (UDS) when explicitly configured.

In order to use a UDS path, you have to explicitly configure the connector to
override the destination URL so that the hostname given in the request URL will
no longer be used to establish the connection:

```php
$connector = new React\Socket\FixedUriConnector(
    'unix:///var/run/docker.sock',
    new React\Socket\UnixConnector($loop)
);

$browser = new Browser($loop, $connector);

$client->get('http://localhost/info')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string)$response->getBody());
});
```

See also the [Unix Domain Sockets (UDS) example](examples/14-unix-domain-sockets.php).

## API

### Browser

The `Clue\React\Buzz\Browser` is responsible for sending HTTP requests to your HTTP server
and keeps track of pending incoming HTTP responses.
It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage).

```php
$loop = React\EventLoop\Factory::create();

$browser = new Clue\React\Buzz\Browser($loop);
```

If you need custom connector settings (DNS resolution, TLS parameters, timeouts,
proxy servers etc.), you can explicitly pass a custom instance of the
[`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface):

```php
$connector = new React\Socket\Connector($loop, array(
    'dns' => '127.0.0.1',
    'tcp' => array(
        'bindto' => '192.168.10.1:0'
    ),
    'tls' => array(
        'verify_peer' => false,
        'verify_peer_name' => false
    )
));

$browser = new Clue\React\Buzz\Browser($loop, $connector);
```

#### get()

The `get(string|UriInterface $url, array $headers = array()): PromiseInterface<ResponseInterface>` method can be used to
send an HTTP GET request.

```php
$browser->get($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump((string)$response->getBody());
});
```

See also [example 01](examples/01-google.php).

#### post()

The `post(string|UriInterface $url, array $headers = array(), string|ReadableStreamInterface $contents = ''): PromiseInterface<ResponseInterface>` method can be used to
send an HTTP POST request.

```php
$browser->post(
    $url,
    [
        'Content-Type' => 'application/json'
    ],
    json_encode($data)
)->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump(json_decode((string)$response->getBody()));
});
```

See also [example 04](examples/04-post-json.php).

This method will automatically add a matching `Content-Length` request
header if the outgoing request body is a `string`. If you're using a
streaming request body (`ReadableStreamInterface`), it will default to
using `Transfer-Encoding: chunked` or you have to explicitly pass in a
matching `Content-Length` request header like so:

```php
$body = new React\Stream\ThroughStream();
$loop->addTimer(1.0, function () use ($body) {
    $body->end("hello world");
});

$browser->post($url, array('Content-Length' => '11'), $body);
```

#### head()

The `head(string|UriInterface $url, array $headers = array()): PromiseInterface<ResponseInterface>` method can be used to
send an HTTP HEAD request.

```php
$browser->head($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders());
});
```

#### patch()

The `patch(string|UriInterface $url, array $headers = array(), string|ReadableStreamInterface $contents = ''): PromiseInterface<ResponseInterface>` method can be used to
send an HTTP PATCH request.

```php
$browser->patch(
    $url,
    [
        'Content-Type' => 'application/json'
    ],
    json_encode($data)
)->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump(json_decode((string)$response->getBody()));
});
```

This method will automatically add a matching `Content-Length` request
header if the outgoing request body is a `string`. If you're using a
streaming request body (`ReadableStreamInterface`), it will default to
using `Transfer-Encoding: chunked` or you have to explicitly pass in a
matching `Content-Length` request header like so:

```php
$body = new React\Stream\ThroughStream();
$loop->addTimer(1.0, function () use ($body) {
    $body->end("hello world");
});

$browser->patch($url, array('Content-Length' => '11'), $body);
```

#### put()

The `put(string|UriInterface $url, array $headers = array()): PromiseInterface<ResponseInterface>` method can be used to
send an HTTP PUT request.

```php
$browser->put(
    $url,
    [
        'Content-Type' => 'text/xml'
    ],
    $xml->asXML()
)->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump((string)$response->getBody());
});
```

See also [example 05](examples/05-put-xml.php).

This method will automatically add a matching `Content-Length` request
header if the outgoing request body is a `string`. If you're using a
streaming request body (`ReadableStreamInterface`), it will default to
using `Transfer-Encoding: chunked` or you have to explicitly pass in a
matching `Content-Length` request header like so:

```php
$body = new React\Stream\ThroughStream();
$loop->addTimer(1.0, function () use ($body) {
    $body->end("hello world");
});

$browser->put($url, array('Content-Length' => '11'), $body);
```

#### delete()

The `delete(string|UriInterface $url, array $headers = array()): PromiseInterface<ResponseInterface>` method can be used to
send an HTTP DELETE request.

```php
$browser->delete($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump((string)$response->getBody());
});
```

#### submit()

The `submit(string|UriInterface $url, array $fields, array $headers = array(), string $method = 'POST'): PromiseInterface<ResponseInterface>` method can be used to
submit an array of field values similar to submitting a form (`application/x-www-form-urlencoded`).

```php
$browser->submit($url, array('user' => 'test', 'password' => 'secret'));
```

#### send()

The `send(RequestInterface $request): PromiseInterface<ResponseInterface>` method can be used to
send an arbitrary instance implementing the [`RequestInterface`](#requestinterface) (PSR-7).

The preferred way to send an HTTP request is by using the above request
methods, for example the `get()` method to send an HTTP `GET` request.

As an alternative, if you want to use a custom HTTP request method, you
can use this method:

```php
$request = new Request('OPTIONS', $url);

$browser->send($request)->then(…);
```

This method will automatically add a matching `Content-Length` request
header if the size of the outgoing request body is known and non-empty.
For an empty request body, if will only include a `Content-Length: 0`
request header if the request method usually expects a request body (only
applies to `POST`, `PUT` and `PATCH`).

#### withOptions()

The `withOptions(array $options): Browser` method can be used to
change the options to use:

The [`Browser`](#browser) class exposes several options for the handling of
HTTP transactions. These options resemble some of PHP's
[HTTP context options](https://www.php.net/manual/en/context.http.php) and
can be controlled via the following API (and their defaults):

```php
$newBrowser = $browser->withOptions(array(
    'timeout' => null,
    'followRedirects' => true,
    'maxRedirects' => 10,
    'obeySuccessCode' => true,
    'streaming' => false,
));
```

See also [timeouts](#timeouts), [redirects](#redirects) and
[streaming](#streaming-response) for more details.

Notice that the [`Browser`](#browser) is an immutable object, i.e. this
method actually returns a *new* [`Browser`](#browser) instance with the
options applied.

#### withBase()

The `withBase(string|UriInterface $baseUri): Browser` method can be used to
change the base URL used to resolve relative URLs to.

```php
$newBrowser = $browser->withBase('http://api.example.com/v3');
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withBase()` method
actually returns a *new* [`Browser`](#browser) instance with the given base URL applied.

Any requests to relative URLs will then be processed by first prepending
the (absolute) base URL.
Please note that this merely prepends the base URL and does *not* resolve
any relative path references (like `../` etc.).
This is mostly useful for (RESTful) API calls where all endpoints (URLs)
are located under a common base URL scheme.

```php
// will request http://api.example.com/v3/example
$newBrowser->get('/example')->then(…);
```

#### withoutBase()

The `withoutBase(): Browser` method can be used to
remove the base URL.

```php
$newBrowser = $browser->withoutBase();
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withoutBase()` method
actually returns a *new* [`Browser`](#browser) instance without any base URL applied.

See also [`withBase()`](#withbase).

#### withProtocolVersion()

The `withProtocolVersion(string $protocolVersion): Browser` method can be used to
change the HTTP protocol version that will be used for all subsequent requests.

All the above [request methods](#request-methods) default to sending
requests as HTTP/1.1. This is the preferred HTTP protocol version which
also provides decent backwards-compatibility with legacy HTTP/1.0
servers. As such, there should rarely be a need to explicitly change this
protocol version.

If you want to explicitly use the legacy HTTP/1.0 protocol version, you
can use this method:

```php
$newBrowser = $browser->withProtocolVersion('1.0');

$newBrowser->get($url)->then(…);
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. this
method actually returns a *new* [`Browser`](#browser) instance with the
new protocol version applied.

### ResponseInterface

The `Psr\Http\Message\ResponseInterface` represents the incoming response received from the [`Browser`](#browser).

This is a standard interface defined in
[PSR-7: HTTP message interfaces](https://www.php-fig.org/psr/psr-7/), see its
[`ResponseInterface` definition](https://www.php-fig.org/psr/psr-7/#3-3-psr-http-message-responseinterface)
which in turn extends the
[`MessageInterface` definition](https://www.php-fig.org/psr/psr-7/#3-1-psr-http-message-messageinterface).

### RequestInterface

The `Psr\Http\Message\RequestInterface` represents the outgoing request to be sent via the [`Browser`](#browser).

This is a standard interface defined in
[PSR-7: HTTP message interfaces](https://www.php-fig.org/psr/psr-7/), see its
[`RequestInterface` definition](https://www.php-fig.org/psr/psr-7/#3-2-psr-http-message-requestinterface)
which in turn extends the
[`MessageInterface` definition](https://www.php-fig.org/psr/psr-7/#3-1-psr-http-message-messageinterface).

### UriInterface

The `Psr\Http\Message\UriInterface` represents an absolute or relative URI (aka URL).

This is a standard interface defined in
[PSR-7: HTTP message interfaces](https://www.php-fig.org/psr/psr-7/), see its
[`UriInterface` definition](https://www.php-fig.org/psr/psr-7/#3-5-psr-http-message-uriinterface).

### ResponseException

The `ResponseException` is an `Exception` sub-class that will be used to reject
a request promise if the remote server returns a non-success status code
(anything but 2xx or 3xx).
You can control this behavior via the ["obeySuccessCode" option](#withoptions).

The `getCode(): int` method can be used to
return the HTTP response status code.

The `getResponse(): ResponseInterface` method can be used to
access its underlying [`ResponseInterface`](#responseinterface) object.

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
$ composer require clue/buzz-react:^2.8.2
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

The test suite also contains a number of functional integration tests that send
test HTTP requests against the online service http://httpbin.org and thus rely
on a stable internet connection.
If you do not want to run these, they can simply be skipped like this:

```bash
$ php vendor/bin/phpunit --exclude-group online
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.
