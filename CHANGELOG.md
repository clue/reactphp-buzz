# Changelog

## 0.4.1 (2015-09-05)

* Fix: Replace URI placeholders before applying base URI, in order to avoid
  duplicate slashes introduced due to URI placeholders.
  ([#48](https://github.com/clue/php-buzz-react/pull/48))

  ```php
// returns "http://example.com/path" instead of "http://example.com//path"
$browser = $browser->withBase('http://example.com/');
echo $browser->resolve('{+path}', array('path' => '/path'));

// returns "http://example.com/path?q=test" instead of "http://example.com/path/?q=test"
$browser = $browser->withBase('http://example.com/path');
echo $browser->resolve('{?q}', array('q' => 'test'));
```

## 0.4.0 (2015-08-09)

* Feature: Resolve relative URIs, add withBase() and resolve()
  ([#41](https://github.com/clue/php-buzz-react/pull/41), [#44](https://github.com/clue/php-buzz-react/pull/44))

  ```php
$browser = $browser->withBase('http://example.com/');
$browser->post('/');
```

* Feature: Resolve URI template placeholders according to RFC 6570
  ([#42](https://github.com/clue/php-buzz-react/pull/42), [#44](https://github.com/clue/php-buzz-react/pull/44))

  ```php
$browser->post($browser->resolve('/{+path}{?version}', array(
    'path' => 'demo.json',
    'version' => '4'
)));
```

* Feature: Resolve and follow redirects to relative URIs
  ([#45](https://github.com/clue/php-buzz-react/pull/45))

* Feature / BC break: Simplify Request and Response objects.
  Remove Browser::request(), use Browser::send() instead.
  ([#37](https://github.com/clue/php-buzz-react/pull/37))
  
  ```php
// old
$browser->request('GET', 'http://www.example.com/');

// new
$browser->send(new Request('GET', 'http://www.example.com/'));
```

* Feature / Bc break: Enforce absolute URIs via new Uri class
  ([#40](https://github.com/clue/php-buzz-react/pull/40), [#44](https://github.com/clue/php-buzz-react/pull/44))

* Feature: Add Browser::withSender() method
  ([#38](https://github.com/clue/php-buzz-react/pull/38))

* Feature: Add Sender::createFromLoopDns() function
  ([#39](https://github.com/clue/php-buzz-react/pull/39))

* Improve documentation and test suite

## 0.3.0 (2015-06-14)

* Feature: Expose Response object in case of HTTP errors
  ([#35](https://github.com/clue/php-buzz-react/pull/35))

* Feature: Add experimental `Transaction` options via `Browser`
  ([#25](https://github.com/clue/php-buzz-react/pull/25))

* Feature: Add experimental streaming API
  ([#31](https://github.com/clue/php-buzz-react/pull/31))

* Feature: Automatically assign a "Content-Length" header for outgoing `Request`s
  ([#29](https://github.com/clue/php-buzz-react/pull/29))

* Feature: Add `Message::getHeader()`, it is now available on both `Request` and `Response`
  ([#28](https://github.com/clue/php-buzz-react/pull/28))

## 0.2.0 (2014-11-30)

* Feature: Support communication via UNIX domain sockets
  ([#20](https://github.com/clue/php-buzz-react/pull/20))

* Fix: Detect immediately failing connection attempt 
  ([#19](https://github.com/clue/php-buzz-react/issues/19))

## 0.1.2 (2014-10-28)

* Fix: Strict warning when accessing a single header value
  ([#18](https://github.com/clue/php-buzz-react/pull/18) by @masakielastic)

## 0.1.1 (2014-05-31)

* Compatibility with React PHP v0.4 (compatibility with v0.3 preserved)
  ([#11](https://github.com/clue/reactphp-buzz/pull/11))

## 0.1.0 (2014-05-27)

* First tagged release

## 0.0.0 (2013-09-01)

* Initial concept
