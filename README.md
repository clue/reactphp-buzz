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
  ReactPHP's standard [promises](#promises) and [streaming interfaces](#streaming).
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](https://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  Builds on top of well-tested components and well-established concepts instead of reinventing the wheel.
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested in the *real world*.

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Browser](#browser)
    * [Methods](#methods)
    * [Promises](#promises)
    * [Cancellation](#cancellation)
    * [Timeouts](#timeouts)
    * [Authentication](#authentication)
    * [Redirects](#redirects)
    * [Blocking](#blocking)
    * [Streaming](#streaming)
    * [submit()](#submit)
    * [send()](#send)
    * [withOptions()](#withoptions)
    * [withBase()](#withbase)
    * [withoutBase()](#withoutbase)
  * [ResponseInterface](#responseinterface)
  * [RequestInterface](#requestinterface)
  * [UriInterface](#uriinterface)
  * [ResponseException](#responseexception)
* [Advanced](#advanced)
  * [HTTP proxy](#http-proxy)
  * [SOCKS proxy](#socks-proxy)
  * [Unix domain sockets](#unix-domain-sockets)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Quickstart example

Once [installed](#install), you can use the following code to access a
HTTP webserver and send some simple HTTP GET requests:

```php
$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$client->get('http://www.google.com/')->then(function (ResponseInterface $response) {
    var_dump($response->getHeaders(), (string)$response->getBody());
});

$loop->run();
```

See also the [examples](examples).

## Usage

### Browser

The `Browser` is responsible for sending HTTP requests to your HTTP server
and keeps track of pending incoming HTTP responses.
It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage).

```php
$loop = React\EventLoop\Factory::create();

$browser = new Browser($loop);
```

If you need custom connector settings (DNS resolution, TLS parameters, timeouts,
proxy servers etc.), you can explicitly pass a custom instance of the
[`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface):

```php
$connector = new \React\Socket\Connector($loop, array(
    'dns' => '127.0.0.1',
    'tcp' => array(
        'bindto' => '192.168.10.1:0'
    ),
    'tls' => array(
        'verify_peer' => false,
        'verify_peer_name' => false
    )
));

$browser = new Browser($loop, $connector);
```

#### Methods

The `Browser` offers several methods that resemble the HTTP protocol methods:

```php
$browser->get($url, array $headers = array());
$browser->head($url, array $headers = array());
$browser->post($url, array $headers = array(), string|ReadableStreamInterface $content = '');
$browser->delete($url, array $headers = array(), string|ReadableStreamInterface $content = '');
$browser->put($url, array $headers = array(), string|ReadableStreamInterface $content = '');
$browser->patch($url, array $headers = array(), string|ReadableStreamInterface $content = '');
```

All the above methods default to sending requests as HTTP/1.0.
If you need a custom HTTP protocol method or version, you can use the [`send()`](#send) method.

Each of the above methods supports async operation and either *resolves* with a [`ResponseInterface`](#responseinterface) or
*rejects* with an `Exception`.
Please see the following chapter about [promises](#promises) for more details.

#### Promises

Sending requests is async (non-blocking), so you can actually send multiple requests in parallel.
The `Browser` will respond to each request with a [`ResponseInterface`](#responseinterface) message, the order is not guaranteed.
Sending requests uses a [Promise](https://github.com/reactphp/promise)-based interface that makes it easy to react to when a transaction is fulfilled (i.e. either successfully resolved or rejected with an error):

```php
$browser->get($url)->then(
    function (ResponseInterface $response) {
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

You may also want to look into the [streaming API](#streaming):

* If you're dealing with lots of concurrent requests (100+) or
* If you want to process individual data chunks as they happen (without having to wait for the full response body) or
* If you're expecting a big response body size (1 MiB or more, for example when downloading binary files) or
* If you're unsure about the response body size (better be safe than sorry when accessing arbitrary remote HTTP endpoints and the response body size is unknown in advance).

#### Cancellation

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

#### Timeouts

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
disable following redirects altogether) and also [streaming](#streaming) below 
to not take receiving large response bodies into account for this timeout.

You can use the [`timeout` option](#withoptions) to pass a custom timeout value
in seconds like this:

```php
$browser = $browser->withOptions(array(
    'timeout' => 10.0
));

$browser->get($uri)->then(function (ResponseInterface $response) {
    // response received within 10 seconds maximum
    var_dump($response->getHeaders());
});
```

Similarly, you can use a negative timeout value to not apply a timeout at all
or use a `null` value to restore the default handling. Note that the underlying
connection may still impose a different timeout value. See also
[`Browser`](#browser) above and [`withOptions()`](#withoptions) for more details.

#### Authentication

This library supports [HTTP Basic Authentication](https://en.wikipedia.org/wiki/Basic_access_authentication)
using the `Authorization: Basic …` request header or allows you to set an explicit
`Authorization` request header.

By default, this library does not include an outgoing `Authorization` request
header. If the server requires authentication, if may return a `401` (Unauthorized)
status code which will reject the request by default (see also
[`obeySuccessCode` option](#withoptions) below).

In order to pass authentication details, you can simple pass the username and
password as part of the request URI like this:

```php
$promise = $browser->get('https://user:pass@example.com/api');
```

Note that special characters in the authentication details have to be
percent-encoded, see also [`rawurlencode()`](http://php.net/rawurlencode).
This example will automatically pass the base64-encoded authentiation details
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

#### Redirects

By default, this library follows any redirects and obeys `3xx` (Redirection)
status codes using the `Location` response header from the remote server.
The promise will be resolved with the last response from the chain of redirects.
Except for a few specific request headers listed below, the redirected requests
will include the exact same request headers as the original request.

```php
$browser->get($uri, $headers)->then(function (ResponseInterface $response) {
    // the final response will end up here
    var_dump($response->getHeaders());
});
```

If the original request contained a request body, this request body will never
be passed to the redirected request. Accordingly, each redirected request will
remove any `Content-Length` and `Content-Type` request headers.

If the original request used HTTP authentication with an `Authorization` request
header, this request header will only be passed as part of the redirected
request if the redirected URI is using the same host. In other words, the
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

$browser->get($uri)->then(function (ResponseInterface $response) {
    // any redirects will now end up here
    var_dump($response->getHeaders());
});
```

See also [`withOptions()`](#withoptions) for more details.

#### Blocking

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
[streaming API](#streaming).

#### Streaming

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
$streamingBrowser->get($url)->then(function (ResponseInterface $response) {
    $body = $response->getBody();
    /* @var $body \React\Stream\ReadableStreamInterface */
    
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

See also the [stream bandwidth example](examples/91-stream-bandwidth.php) and
the [stream forwarding example](examples/21-stream-forwarding.php).

You can invoke the following methods on the message body:

```php
$body->on($event, $callback);
$body->eof();
$body->isReadable();
$body->pipe(WritableStreamInterface $dest, array $options = array());
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

function download($url) {
    return Stream\unwrapReadable($streamingBrowser->get($url)->then(function (ResponseInterface $response) {
        return $response->getBody();
    });
}

$stream = download($url);
$stream->on('data', function ($data) {
    echo $data;
});
```

Besides streaming the response body, you can also stream the request body.
This can be useful if you want to send big POST requests (uploading files etc.)
or process many outgoing streams at once.
Instead of passing the body as a string, you can simply pass an instance
implementing ReactPHP's [`ReadableStreamInterface`](https://github.com/reactphp/stream#readablestreaminterface)
to the [HTTP methods](#methods) like this:

```php
$browser->post($url, array(), $stream)->then(function (ResponseInterface $response) {
    echo 'Successfully sent.';
});
```

#### submit()

The `submit($url, array $fields, $headers = array(), $method = 'POST'): PromiseInterface<ResponseInterface>` method can be used to
submit an array of field values similar to submitting a form (`application/x-www-form-urlencoded`).

```php
$browser->submit($url, array('user' => 'test', 'password' => 'secret'));
```

#### send()

The `send(RequestInterface $request): PromiseInterface<ResponseInterface>` method can be used to
send an arbitrary instance implementing the [`RequestInterface`](#requestinterface) (PSR-7).

All the above [predefined methods](#methods) default to sending requests as HTTP/1.0.
If you need a custom HTTP protocol method or version, then you may want to use this
method:

```php
$request = new Request('OPTIONS', $url);
$request = $request->withProtocolVersion('1.1');

$browser->send($request)->then(…);
```

#### withOptions()

The `withOptions(array $options): Browser` method can be used to
change the options to use:

The [`Browser`](#browser) class exposes several options for the handling of
HTTP transactions. These options resemble some of PHP's
[HTTP context options](http://php.net/manual/en/context.http.php) and
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
[streaming](#streaming) for more details.

Notice that the [`Browser`](#browser) is an immutable object, i.e. this
method actually returns a *new* [`Browser`](#browser) instance with the
options applied.

#### withBase()

The `withBase($baseUri): Browser` method can be used to
change the base URI used to resolve relative URIs to.

```php
$newBrowser = $browser->withBase('http://api.example.com/v3');
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withBase()` method
actually returns a *new* [`Browser`](#browser) instance with the given base URI applied.

Any requests to relative URIs will then be processed by first prepending
the (absolute) base URI.
Please note that this merely prepends the base URI and does *not* resolve
any relative path references (like `../` etc.).
This is mostly useful for (RESTful) API calls where all endpoints (URIs)
are located under a common base URI scheme.

```php
// will request http://api.example.com/v3/example
$newBrowser->get('/example')->then(…);
```

#### withoutBase()

The `withoutBase(): Browser` method can be used to
remove the base URI.

```php
$newBrowser = $browser->withoutBase();
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withoutBase()` method
actually returns a *new* [`Browser`](#browser) instance without any base URI applied.

See also [`withBase()`](#withbase).

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

## Advanced

### HTTP proxy

You can also establish your outgoing connections through an HTTP CONNECT proxy server
by adding a dependency to [clue/reactphp-http-proxy](https://github.com/clue/reactphp-http-proxy).

HTTP CONNECT proxy servers (also commonly known as "HTTPS proxy" or "SSL proxy")
are commonly used to tunnel HTTPS traffic through an intermediary ("proxy"), to
conceal the origin address (anonymity) or to circumvent address blocking
(geoblocking). While many (public) HTTP CONNECT proxy servers often limit this
to HTTPS port`443` only, this can technically be used to tunnel any TCP/IP-based
protocol, such as plain HTTP and TLS-encrypted HTTPS.

See also the [HTTP CONNECT proxy example](examples/11-http-proxy.php).

### SOCKS proxy

You can also establish your outgoing connections through a SOCKS proxy server
by adding a dependency to [clue/reactphp-socks](https://github.com/clue/reactphp-socks).

The SOCKS proxy protocol family (SOCKS5, SOCKS4 and SOCKS4a) is commonly used to
tunnel HTTP(S) traffic through an intermediary ("proxy"), to conceal the origin
address (anonymity) or to circumvent address blocking (geoblocking). While many
(public) SOCKS proxy servers often limit this to HTTP(S) port `80` and `443`
only, this can technically be used to tunnel any TCP/IP-based protocol.

See also the [SOCKS proxy example](examples/12-socks-proxy.php).

### Unix domain sockets

By default, this library supports transport over plaintext TCP/IP and secure
TLS connections for the `http://` and `https://` URI schemes respectively.
This library also supports Unix domain sockets (UDS) when explicitly configured.

In order to use a UDS path, you have to explicitly configure the connector to
override the destination URI so that the hostname given in the request URI will
no longer be used to establish the connection:

```php
$connector = new \React\Socket\FixedUriConnector(
    'unix:///var/run/docker.sock',
    new \React\Socket\UnixConnector($loop)
);

$browser = new Browser($loop, $connector);

$client->get('http://localhost/info')->then(function (ResponseInterface $response) {
    var_dump($response->getHeaders(), (string)$response->getBody());
});
```

See also the [Unix Domain Sockets (UDS) example](examples/13-unix-domain-sockets.php).

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
$ composer require clue/buzz-react:^2.5
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
