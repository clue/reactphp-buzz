# Changelog

## 1.0.0 (2016-08-09)

* First stable release, now following SemVer

* Improve documentation and usage examples

> Contains no other changes, so it's actually fully compatible with the v0.5.0 release.

## 0.5.0 (2016-04-02)

* Feature / BC break: Implement PSR-7 http-message interfaces
  (#54 by @clue)
  
  Replace custom `Message`, `Request`, `Response` and `Uri` classes with
  common PSR-7 interfaces:

  ```php
  // old
  $browser->get($uri)->then(function (Response $response) {
      echo 'Test: ' . $response->getHeader('X-Test');
      echo 'Body: ' . $response->getBody();
  });
  
  // new
  $browser->get($uri)->then(function (ResponseInterface $response) {
      if ($response->hasHeader('X-Test')) {
          echo 'Test: ' . $response->getHeaderLine('X-Test');
      }
      echo 'Body: ' . $response->getBody();
  });
  ```

* Feature: Add streaming API
  (#56 by @clue)

  ```php
  $browser = $browser->withOptions(array('streaming' => true));
  $browser->get($uri)->then(function (ResponseInterface $response) {
      $response->getBody()->on('data', function($chunk) {
          echo $chunk . PHP_EOL;
      });
  });
  ```

* Remove / BC break: Remove `Browser::resolve()` because it's now fully decoupled
  (#55 by @clue)

  If you need this feature, consider explicitly depending on rize/uri-template
  instead:

  ```bash
  $ composer require rize/uri-template
  ```

* Use clue/block-react and new Promise API in order to simplify tests
  (#53 by @clue)

## 0.4.2 (2016-03-25)

* Support advanced connection options with newest SocketClient (TLS/HTTPS and socket options)
  (#51 by @clue)

* First class support for PHP 5.3 through PHP 7 and HHVM
  (#52 by @clue)

## 0.4.1 (2015-09-05)

* Fix: Replace URI placeholders before applying base URI, in order to avoid
  duplicate slashes introduced due to URI placeholders.
  ([#48](https://github.com/clue/php-buzz-react/pull/48))

  ```php
  // now correctly returns "http://example.com/path"
  // instead of previous   "http://example.com//path"
  $browser = $browser->withBase('http://example.com/');
  echo $browser->resolve('{+path}', array('path' => '/path'));
  
  // now correctly returns "http://example.com/path?q=test"
  // instead of previous   "http://example.com/path/?q=test"
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
