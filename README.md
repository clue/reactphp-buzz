# clue/http-react [![Build Status](https://travis-ci.org/clue/http-react.png?branch=master)](https://travis-ci.org/clue/http-react)

Simple async HTTP client for fetching URLs, talking to APIs, downloads,
redirects, cache, etc.

This provides a higher level API that is easy to use in order to process
(i.e. download or stream) multiple HTTP requests concurrently without having to
mess with most of the low level details of the underlying
[react/http-client](https://gitub.com/reactphp/http-client).

> Note: This project is in early alpha stage! Feel free to report any issues you encounter.

## Quickstart example

Once [installed](#install), you can use the following code to connect to your
local http server and send some requests:

```php

$factory = new Factory($loop);
$client = $factory->createClient();

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
        "clue/http-react": "dev-master"
    }
}
```

## License

MIT

