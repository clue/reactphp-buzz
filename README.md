# clue/buzz-react [![Build Status](https://travis-ci.org/clue/php-buzz-react.svg?branch=master)](https://travis-ci.org/clue/php-buzz-react)

Simple async HTTP client for concurrently interacting with multiple HTTP servers,
fetching URLs, talking to RESTful APIs, downloading files, following redirects
etc. all at the same time.

This library is heavily inspired by the great
[kriswallsmith/Buzz](https://github.com/kriswallsmith/Buzz)
project. However, instead of blocking on each request, it relies on
[React PHP's EventLoop](https://github.com/reactphp/event-loop) to process
multiple requests in parallel.

React PHP also provides the package
[react/http-client](https://github.com/reactphp/http-client) which provides a
streaming HTTP client implementation.
That package is ideally suited for accessing streaming APIs or processing huge
requests, but requires quite a bit of boilerplate code if all you want to do is
to access a *normal* website or your average RESTful API.

As such, this projects aims at providing a higher level API that is easy to use
in order to process multiple HTTP requests concurrently without having to
mess with most of the low level details of the underlying
[react/http-client](https://github.com/reactphp/http-client).

> Note: This project is in beta stage! Feel free to report any issues you encounter.

## Quickstart example

Once [installed](#install), you can use the following code to access a
HTTP webserver and send some simple HTTP GET requests:

```php
$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$client->get('http://www.google.com/')->then(function (Response $result) {
    var_dump($result->getHeaders(), $result->getBody());
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

If you need a custom HTTP protocol method, you can use the [`send()`](#send) method.

Each of the above methods supports async operation and either *resolves* with a [`Response`](#response) or
*rejects* with an `Exception`.
Please see the following chapter about [promises](#promises) for more details.

#### Promises

Sending requests is async (non-blocking), so you can actually send multiple requests in parallel.
The `Browser` will respond to each request with a [`Response`](#response) message, the order is not guaranteed.
Sending requests uses a [Promise](https://github.com/reactphp/promise)-based interface that makes it easy to react to when a transaction is fulfilled (i.e. either successfully resolved or rejected with an error):

```php
$browser->get($url)->then(
    function ($response) {
        var_dump('Response received', $response);
    },
    function (Exception $error) {
        var_dump('There was an error', $error->getMessage());
    }
});
```

If this looks strange to you, you can also use the more traditional [blocking API](#blocking).

#### Blocking

As stated above, this library provides you a powerful, async API by default.

If, however, you want to integrate this into your traditional, blocking environment,
you should look into also using [clue/block-react](https://github.com/clue/php-block-react).

The resulting blocking code could look something like this:

```php
$loop = React\EventLoop\Factory::create();
$browser = new Clue\React\Buzz\Browser($loop);

$promise = $browser->get('http://example.com/');

try {
    $response = Clue\React\Block\await($promise);
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

$responses = Clue\React\Block\awaitAll($promises);
```

Refer to [clue/block-react](https://github.com/clue/php-block-react#readme) for more details.

#### submit()

The `submit($url, array $fields, $headers = array(), $method = 'POST')` method can be used to submit an array of field values similar to submitting a form (`application/x-www-form-urlencoded`).

#### send()

The `send(Request $request)` method can be used to send an arbitrary [`Request` object](#request).

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

See also [`resolve()`](#resolve).

#### withoutBase()

The `withoutBase()` method can be used to remove the base URI.

```php
$newBrowser = $browser->withoutBase();
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withoutBase()` method
actually returns a *new* [`Browser`](#browser) instance without any base URI applied.

See also [`withBase()`](#withbase).

#### resolve()

The `resolve($uri, array $parameters = array())` method can be used to resolve the given relative URI to
an absolute URI by appending it behind the configured base URI.
It also replaces URI template placeholders with the given `$parameters`
according to [RFC 6570](http://tools.ietf.org/html/rfc6570).
It returns a new [`Uri`](#uri) instance which can then be passed
to the [HTTP methods](#methods).

URI template placeholders in the given URI string will be replaced according to
[RFC 6570](http://tools.ietf.org/html/rfc6570):

```php
echo $browser->resolve('http://example.com/{?first,second,third}', array(
    'first' => 'a',
    'third' => 'c'
));
// http://example.com/?first=a&third=c
```

If you pass in a relative URI string, then it will be resolved relative to the
configured base URI.
Please note that this merely prepends the base URI and does *not* resolve any
relative path references (like `../` etc.).
This is mostly useful for API calls where all endpoints (URIs) are located
under a common base URI:

```php
$newBrowser = $browser->withBase('http://api.example.com/v3');

echo $newBrowser->resolve('/example');
// http://api.example.com/v3/example
```

The URI template placeholders can also be combined with a base URI like this:

```php
echo $newBrowser->resolve('/fetch{/file}{?version,tag}', array(
    'file' => 'example',
    'version' => 1.0,
    'tag' => 'just testing'
));
// http://api.example.com/v3/fetch/example?version=1.0&tag=just%20testing
```

This uses the excellent [rize/uri-template](https://github.com/rize/UriTemplate) library under the hood.
Please refer to [its documentation](https://github.com/rize/UriTemplate#usage) or
[RFC 6570](http://tools.ietf.org/html/rfc6570) for more details.

Trying to resolve anything that does not live under the same base URI will
result in an `UnexpectedValueException`:

```php
$newBrowser->resolve('http://www.example.com/');
// throws UnexpectedValueException
```

Similarily, if you do not have a base URI configured, passing a relative URI
will result in an `InvalidArgumentException`:

```php
$browser->resolve('/example');
// throws InvalidArgumentException
```

### Message

The `Message` is an abstract base class for the [`Response`](#response) and [`Request`](#request).
It provides a common interface for these message types.

See its [class outline](src/Message/Message.php) for more details.

### Response

The `Response` value object represents the incoming response received from the [`Browser`](#browser).
It shares all properties of the [`Message`](#message) parent class.

See its [class outline](src/Message/Response.php) for more details.

### Request

The `Request` value object represents the outgoing request to be sent via the [`Browser`](#browser).
It shares all properties of the [`Message`](#message) parent class.

See its [class outline](src/Message/Request.php) for more details.

#### getUri()

The `getUri()` method can be used to get its [`Uri`](#uri) instance.

### Uri

An `Uri` represents an absolute URI (aka URL).

By definition of this library, an `Uri` instance is always absolute and can not contain any placeholders.
As such, any incomplete/relative URI will be rejected with an `InvalidArgumentException`.

Each [`Request`](#request) contains a (full) absolute request URI.

```
$request = new Request('GET', 'http://www.google.com/');
$uri = $request->getUri();

assert('http' == $uri->getScheme());
assert('www.google.com' == $uri->getHost());
assert('/' == $uri->getPath());
```

See its [class outline](src/Message/Uri.php) for more details.

Internally, this class uses the excellent [ml/iri](https://github.com/lanthaler/IRI) library under the hood.

### ResponseException

The `ResponseException` is an `Exception` sub-class that will be used to reject
a request promise if the remote server returns a non-success status code
(anything but 2xx or 3xx).
You can control this behavior via the ["obeySuccessCode" option](#options).

The `getCode()` method can be used to return the HTTP response status code.

The `getResponse()` method can be used to access its underlying [`Response`](#response) object.

## Advanced

### Sender

The `Sender` is responsible for passing the [`Request`](#request) objects to
the underlying [`HttpClient`](https://github.com/reactphp/http-client) library
and keeps track of its transmission and converts its reponses back to [`Response`](#response) objects.

It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage)
and the default [`Connector`](https://github.com/reactphp/socket-client) and [DNS `Resolver`](https://github.com/reactphp/dns).

See also [`Browser::withSender()`](#withsender) for changing the `Sender` instance during runtime.

### DNS

The [`Sender`](#sender) is also resposible for creating the underlying TCP/IP
connection to the remove HTTP server and hence has to orchestrate DNS lookups.
By default, it uses a `Connector` instance which uses Google's public DNS servers
(`8.8.8.8`).

If you need custom DNS settings, you explicitly create a [`Sender`](#sender) instance
with your DNS server address (or `React\Dns\Resolver` instance) like this:

```php
$dns = '127.0.0.1';
$sender = Sender::createFromLoopDns($loop, $dns);
$browser = $browser->withSender($sender);
```

See also [`Browser::withSender()`](#withsender) for more details.

### SOCKS proxy

You can also establish your outgoing connections through a SOCKS proxy server
by adding a dependency to [clue/socks-react](https://github.com/clue/php-socks-react).

The SOCKS protocol operates at the TCP/IP layer and thus requires minimal effort at the HTTP application layer.
This works for both plain HTTP and SSL encrypted HTTPS requests.

See also the [SOCKS example](examples/socks).

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
    'obeySuccessCode' => true
));
```

Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withOptions()` method
actually returns a *new* [`Browser`](#browser) instance with the options applied.

### Streaming

Note: This API is subject to change.

The [`Sender`](#sender) emits a `progress` event array on its `Promise` that can be used
to intercept the underlying outgoing request stream (`React\HttpClient\Request` in the `requestStream` key)
and the incoming response stream (`React\HttpClient\Response` in the `responseStream` key).

```php
$client->get('http://www.google.com/')->then($handler, null, function ($event) {
    if (isset($event['responseStream'])) {
        /* @var $stream React\HttpClient\Response */
        $stream = $event['responseStream'];
        $stream->on('data', function ($data) { });
    }
});
```

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/buzz-react": "~0.3.0"
    }
}
```

## License

MIT
