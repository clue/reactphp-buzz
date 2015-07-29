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

If you need a custom HTTP protocol method, you can use the [`request()`](#request) method.

#### Processing

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

#### submit()

The `submit($url, array $fields, $headers = array(), $method = 'POST')` method can be used to submit an array of field values similar to submitting a form (`application/x-www-form-urlencoded`).

#### request()

The `request($method, $url, $headers = array(), $content = '')` method can be used to create and send an arbitrary request.

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

### Message

The `Message` is an abstract base class for the [`Response`](#response) and [`Request`](#request).
It provides a common interface for these message types.

#### Response

The `Response` value object represents the incoming response received from the [`Browser`](#browser).
It shares all properties of the [`Message`](#message) parent class.

#### Request

The `Request` value object represents the outgoing request to be sent via the [`Browser`](#browser).
It shares all properties of the [`Message`](#message) parent class.

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
