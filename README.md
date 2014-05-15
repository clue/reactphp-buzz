# clue/buzz-react [![Build Status](https://travis-ci.org/clue/reactphp-buzz.png?branch=master)](https://travis-ci.org/clue/reactphp-buzz)

Simple async HTTP client for concurrently interacting with multiple HTTP servers,
fetching URLs, talking to RESTful APIs, downloading files, following redirects
etc. all at the same time.

This library is heavily inspired by the great
[kriswallsmith/Buzz](https://github.com/kriswallsmith/Buzz)
project. However, instead of blocking on each request, it relies on
[react's EventLoop]((https://gitub.com/reactphp/event-loop) to process
multiple requests in parallel.

This provides a higher level API that is easy to use in order to process
(i.e. download or stream) multiple HTTP requests concurrently without having to
mess with most of the low level details of the underlying
[react/http-client](https://gitub.com/reactphp/http-client).

> Note: This project is in early alpha stage! Feel free to report any issues you encounter.

## Quickstart example

Once [installed](#install), you can use the following code to connect to your
local http server and send some requests:

```php

$client = new Browser($loop);

$client->get('http://www.google.com/')->then(function (BufferedResponse $result) {
    var_dump($result->getHeaders(), $result->getBody());
});

$loop->run();
```

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/buzz-react": "dev-master"
    }
}
```

## License

MIT
