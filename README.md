# clue/buzz-react [![Build Status](https://travis-ci.org/clue/php-buzz-react.svg?branch=master)](https://travis-ci.org/clue/php-buzz-react)

Simple, async PSR-7 HTTP client for concurrently processing any number of HTTP requests,
built on top of [ReactPHP](http://reactphp.org/).

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
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](http://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  Builds on top of well-tested components and well-established concepts instead of reinventing the wheel.
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested in the *real world*

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Browser](#browser)
    * [Methods](#methods)
    * [Promises](#promises)
    * [Blocking](#blocking)
    * [Streaming](#streaming)
    * [submit()](#submit)
    * [send()](#send)
    * [withOptions()](#withoptions)
    * [withSender()](#withsender)
    * [withBase()](#withbase)
    * [withoutBase()](#withoutbase)
  * [ResponseInterface](#responseinterface)
  * [RequestInterface](#requestinterface)
  * [UriInterface](#uriinterface)
  * [ResponseException](#responseexception)
* [Advanced](#advanced)
  * [Sender](#sender)
  * [DNS](#dns)
  * [Connection options](#connection-options)
  * [SOCKS proxy](#socks-proxy)
  * [UNIX domain sockets](#unix-domain-sockets)
  * [Options](#options)
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

If you need custom DNS or proxy settings, you can explicitly pass a
custom [`Sender`](#sender) instance. This is considered *advanced usage*.

#### Methods

The `Browser` offers several methods that resemble the HTTP protocol methods:

```php
$browser->get($url, array $headers = array());
$browser->head($url, array $headers = array());
$browser->post($url, array $headers = array(), $content = '');
$browser->delete($url, array $headers = array(), $content = '');
$browser->put($url, array $headers = array(), $content = '');
$browser->patch($url, array $headers = array(), $content = '');
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
});
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

#### Blocking

As stated above, this library provides you a powerful, async API by default.

If, however, you want to integrate this into your traditional, blocking environment,
you should look into also using [clue/block-react](https://github.com/clue/php-block-react).

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

Please refer to [clue/block-react](https://github.com/clue/php-block-react#readme) for more details.

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
as well as parts of the PSR-7's [`StreamInterface`](http://www.php-fig.org/psr/psr-7/#3-4-psr-http-message-streaminterface).

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

If you want to integrate the streaming response into a higher level API, then
working with Promise objects that resolve with Stream objects is often inconvenient.
Consider looking into also using [clue/promise-stream-react](https://github.com/clue/php-promise-stream-react).
The resulting streaming code could look something like this:

```php
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

The `submit($url, array $fields, $headers = array(), $method = 'POST')` method can be used to submit an array of field values similar to submitting a form (`application/x-www-form-urlencoded`).

#### send()

The `send(RequestInterface $request)` method can be used to send an arbitrary
instance implementing the [`RequestInterface`](#requestinterface) (PSR-7).

All the above [predefined methods](#methods) default to sending requests as HTTP/1.0.
If you need a custom HTTP protocol method or version, then you may want to use this
method:

```php
$request = new Request('OPTIONS', $url);
$request = $request->withProtocolVersion(1.1);

$browser->send($request)->then(…);
```

#### withOptions()

The `withOptions(array $options)` method can be used to change the [options](#options) to use:

```php
$newBrowser = $browser->withOptions($options);
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withOptions()` method
actually returns a *new* [`Browser`](#browser) instance with the [options](#options) applied.

See [options](#options) for more details.

#### withSender()

The `withSender(Sender $sender)` method can be used to change the [`Sender`](#sender) instance to use:

```php
$newBrowser = $browser->withSender($sender);
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withSender()` method
actually returns a *new* [`Browser`](#browser) instance with the given [`Sender`](#sender) applied.

See [`Sender`](#sender) for more details.

#### withBase()

The `withBase($baseUri)` method can be used to change the base URI used to
resolve relative URIs to.

```php
$newBrowser = $browser->withBase('http://api.example.com/v3');
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withBase()` method
actually returns a *new* [`Browser`](#browser) instance with the given base URI applied.

Any requests to relative URIs will then be processed by first prepending the
base URI.
Please note that this merely prepends the base URI and does *not* resolve any
relative path references (like `../` etc.).
This is mostly useful for API calls where all endpoints (URIs) are located
under a common base URI scheme.

```php
// will request http://api.example.com/v3/example
$newBrowser->get('/example')->then(…);
```

#### withoutBase()

The `withoutBase()` method can be used to remove the base URI.

```php
$newBrowser = $browser->withoutBase();
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withoutBase()` method
actually returns a *new* [`Browser`](#browser) instance without any base URI applied.

See also [`withBase()`](#withbase).

### ResponseInterface

The `Psr\Http\Message\ResponseInterface` represents the incoming response received from the [`Browser`](#browser).

This is a standard interface defined in
[PSR-7: HTTP message interfaces](http://www.php-fig.org/psr/psr-7/), see its
[`ResponseInterface` definition](http://www.php-fig.org/psr/psr-7/#3-3-psr-http-message-responseinterface)
which in turn extends the
[`MessageInterface` definition](http://www.php-fig.org/psr/psr-7/#3-1-psr-http-message-messageinterface).

### RequestInterface

The `Psr\Http\Message\RequestInterface` represents the outgoing request to be sent via the [`Browser`](#browser).

This is a standard interface defined in
[PSR-7: HTTP message interfaces](http://www.php-fig.org/psr/psr-7/), see its
[`RequestInterface` definition](http://www.php-fig.org/psr/psr-7/#3-2-psr-http-message-requestinterface)
which in turn extends the
[`MessageInterface` definition](http://www.php-fig.org/psr/psr-7/#3-1-psr-http-message-messageinterface).

### UriInterface

The `Psr\Http\Message\UriInterface` represents an absolute or relative URI (aka URL).

This is a standard interface defined in
[PSR-7: HTTP message interfaces](http://www.php-fig.org/psr/psr-7/), see its
[`UriInterface` definition](http://www.php-fig.org/psr/psr-7/#3-5-psr-http-message-uriinterface).

### ResponseException

The `ResponseException` is an `Exception` sub-class that will be used to reject
a request promise if the remote server returns a non-success status code
(anything but 2xx or 3xx).
You can control this behavior via the ["obeySuccessCode" option](#options).

The `getCode()` method can be used to return the HTTP response status code.

The `getResponse()` method can be used to access its underlying [`ResponseInteface`](#responseinterface) object.

## Advanced

### Sender

The `Sender` is responsible for passing the [`RequestInterface`](#requestinterface) objects to
the underlying [`HttpClient`](https://github.com/reactphp/http-client) library
and keeps track of its transmission and converts its reponses back to [`ResponseInterface`](#responseinterface) objects.

It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage)
and the default [`Connector`](https://github.com/reactphp/socket-client) and [DNS `Resolver`](https://github.com/reactphp/dns).

See also [`Browser::withSender()`](#withsender) for changing the `Sender` instance during runtime.

### DNS

The [`Sender`](#sender) is also responsible for creating the underlying TCP/IP
connection to the remote HTTP server and hence has to orchestrate DNS lookups.
By default, it uses a `Connector` instance which uses Google's public DNS servers
(`8.8.8.8`).

If you need custom DNS settings, you can explicitly create a [`Sender`](#sender) instance
with your DNS server address (or `React\Dns\Resolver` instance) like this:

```php
// new API for react/http 0.5
$connector = new \React\Socket\Connector($loop, array(
    'dns' => '127.0.0.1'
));
$client = new \React\HttpClient\Client($loop, $connector);
$sender = new \Clue\Buzz\Io\Sender($client);
$browser = $browser->withSender($sender);

// deprecated legacy API
$dns = '127.0.0.1';
$sender = Sender::createFromLoopDns($loop, $dns);
$browser = $browser->withSender($sender);
```

See also [`Browser::withSender()`](#withsender) for more details.

### Connection options

If you need custom connector settings (DNS resolution, SSL/TLS parameters, timeouts etc.), you can explicitly pass a
custom instance of the new [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface).

```php
// new API for react/http 0.5
$connector = new \React\Socket\Connector($loop, array(
    'dns' => '127.0.0.1'
));
$client = new \React\HttpClient\Client($loop, $connector);
$sender = new \Clue\Buzz\Io\Sender($client);
$browser = $browser->withSender($sender);
```

If you're still using the deprecated legacy Http component and you need custom
connector settings (DNS resolution, SSL/TLS parameters, timeouts etc.), you can
explicitly pass a custom instance of the
[legacy `ConnectorInterface`](https://github.com/reactphp/socket-client#connectorinterface).

The below examples assume you've installed the latest legacy SocketClient version:

```bash
$ composer require react/socket-client:^0.5
```

You can optionally pass additional
[socket context options](http://php.net/manual/en/context.socket.php)
to the constructor like this:

```php
// deprecated legacy API
// use local DNS server
$dnsResolverFactory = new DnsFactory();
$resolver = $dnsResolverFactory->createCached('127.0.0.1', $loop);

// outgoing connections via interface 192.168.10.1
$tcp = new DnsConnector(
    new TcpConnector($loop, array('bindto' => '192.168.10.1:0')),
    $resolver
);

$sender = Sender::createFromLoopConnectors($loop, $tcp);
$browser = $browser->withSender($sender);
```

You can optionally pass additional
[SSL context options](http://php.net/manual/en/context.ssl.php)
to the constructor like this:

```php
// deprecated legacy API
$ssl = new SecureConnector($tcp, $loop, array(
    'verify_peer' => false,
    'verify_peer_name' => false
));

$sender = Sender::createFromLoopConnectors($loop, $tcp, $ssl);
$browser = $browser->withSender($sender);
```

### SOCKS proxy

You can also establish your outgoing connections through a SOCKS proxy server
by adding a dependency to [clue/socks-react](https://github.com/clue/php-socks-react).

The SOCKS protocol operates at the TCP/IP layer and thus requires minimal effort at the HTTP application layer.
This works for both plain HTTP and SSL encrypted HTTPS requests.

See also the [SOCKS example](examples/11-socks-proxy.php).

### UNIX domain sockets

This library also supports connecting to a local UNIX domain socket path.
You have to explicitly create a [`Sender`](#sender) that passes every request through the
given UNIX domain socket.
For consistency reasons you still have to pass full HTTP URLs for every request,
but the host and port will be ignored when establishing a connection.

```php
$path = 'unix:///tmp/daemon.sock';
$sender = Sender::createFromLoopUnix($loop, $path);
$client = new Browser($loop, $sender);

$client->get('http://localhost/demo');
```

### Options

Note: This API is subject to change.

The [`Browser`](#browser) class exposes several options for the handling of
HTTP transactions. These options resemble some of PHP's
[HTTP context options](http://php.net/manual/en/context.http.php) and
can be controlled via the following API (and their defaults):

```php
$newBrowser = $browser->withOptions(array(
    'followRedirects' => true,
    'maxRedirects' => 10,
    'obeySuccessCode' => true,
    'streaming' => false,
));
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withOptions()` method
actually returns a *new* [`Browser`](#browser) instance with the options applied.

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require clue/buzz-react:^1.1.1
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.4 through current PHP 7+ and
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

MIT
