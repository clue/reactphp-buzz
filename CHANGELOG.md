# Changelog

## 2.5.0 (2018-10-24)

*   Feature: Add HTTP timeout option.
    (#114 by @Rakdar and @clue)

    This now respects PHP's `default_socket_timeout` setting (default 60s) as a
    timeout for sending the outgoing HTTP request and waiting for a successful
    response and will otherwise cancel the pending request and reject its value
    with an Exception. You can now use the [`timeout` option](#withoptions) to
    pass a custom timeout value in seconds like this:

    ```php
    $browser = $browser->withOptions(array(
        'timeout' => 10.0
    ));

    $browser->get($uri)->then(function (ResponseInterface $response) {
        // response received within 10 seconds maximum
        var_dump($response->getHeaders());
    });
    ```

    Similarly, you can use a negative timeout value to not apply a timeout at
    all or use a `null` value to restore the default handling.

*   Improve documentation for `withOptions()` and
    add documentation and example for HTTP CONNECT proxy.
    (#111 and #115 by @clue)

*   Refactor `Browser` to reuse single `Transaction` instance internally
    which now accepts sending individual requests and their options.
    (#113 by @clue)

## 2.4.0 (2018-10-02)

*   Feature / Fix: Support cancellation forwarding and cancelling redirected requests.
    (#110 by @clue)

*   Feature / Fix: Remove `Authorization` request header for redirected cross-origin requests
    and add documentation for HTTP redirects.
    (#108 by @clue)

*   Improve API documentation and add documentation for HTTP authentication and `Authorization` header.
    (#104 and #109 by @clue)

*   Update project homepage.
    (#100 by @clue)

## 2.3.0 (2018-02-09)

*   Feature / Fix: Pass custom request headers when following redirects
    (#91 by @seregazhuk and #96 by @clue)

*   Support legacy PHP 5.3 through PHP 7.2 and HHVM
    (#95 by @clue)

*   Improve documentation
    (#87 by @holtkamp and #93 by @seregazhuk)

*   Improve test suite by adding forward compatibility with PHPUnit 5, PHPUnit 6
    and PHPUnit 7 and explicitly test HTTP/1.1 protocol version.
    (#86 by @carusogabriel and #94 and #97 by @clue)

## 2.2.0 (2017-10-24)

*   Feature: Forward compatibility with freshly released react/promise-stream v1.0
    (#85 by @WyriHaximus)

## 2.1.0 (2017-09-17)

*   Feature: Update minimum required Socket dependency version in order to
    support Unix Domain Sockets (UDS) again,
    support hosts file on all platforms and
    work around sending secure HTTPS requests with PHP < 7.1.4
    (#84 by @clue)

## 2.0.0 (2017-09-16)

A major compatibility release to update this component to support all latest
ReactPHP components!

This update involves a minor BC break due to dropped support for legacy
versions. We've tried hard to avoid BC breaks where possible and minimize impact
otherwise. We expect that most consumers of this package will actually not be
affected by any BC breaks, see below for more details.

*   BC break: Remove deprecated API and mark Sender as @internal only,
    remove all references to legacy SocketClient component and
    remove support for Unix domain sockets (UDS) for now
    (#77, #78, #81 and #83 by @clue)

    >   All of this affects the `Sender` only, which was previously marked as
        "advanced usage" and is now marked `@internal` only. If you've not
        used this class before, then this BC break will not affect you.
        If you've previously used this class, then it's recommended to first
        update to the intermediary v1.4.0 release, which allows you to use a
        standard `ConnectorInterface` instead of the `Sender` and then update
        to this version without causing a BC break.
        If you've previously used Unix domain sockets (UDS), then you're
        recommended to wait for the next version.

*   Feature / BC break: Forward compatibility with future Stream v1.0 and strict stream semantics
    (#79 by @clue)

    >   This component now follows strict stream semantics. This is marked as a
        BC break because this removes undocumented and untested excessive event
        arguments. If you've relied on proper stream semantics as documented
        before, then this BC break will not affect you.

*   Feature: Forward compatibility with future Socket and EventLoop components
    (#80 by @clue)

## 1.4.0 (2017-09-15)

*   Feature: `Browser` accepts `ConnectorInterface` and deprecate legacy `Sender`
    (#76 by @clue)

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

## 1.3.0 (2017-09-08)

*   Feature: Support request cancellation
    (#75 by @clue)

    ```php
    $promise = $browser->get($url);

    $loop->addTimer(2.0, function () use ($promise) {
        $promise->cancel();
    });
    ```

*   Feature: Update react/http-client to v0.5,
    support react/stream v0.6 and react/socket-client v0.7 and drop legacy PHP 5.3 support
    (#74 by @clue)

## 1.2.0 (2017-09-05)

* Feature: Forward compatibility with react/http-client v0.5
  (#72 and #73 by @clue)

  Older HttpClient versions are still supported, but the new version is now
  preferred. Advanced usage with custom connectors now recommends setting up
  the `React\HttpClient\Client` instance explicitly.

  Accordingly, the `Sender::createFromLoopDns()` and
  `Sender::createFromLoopConnectors()` have been marked as deprecated and
  will be removed in future versions.

## 1.1.1 (2017-09-05)

* Restructure examples to ease getting started and
  fix online tests and add option to exclude tests against httpbin.org
  (#67 and #71 by @clue)

* Improve test suite by fixing HHVM build for now again and ignore future HHVM build errors and
  lock Travis distro so new defaults will not break the build
  (#68 and #70 by @clue)

## 1.1.0 (2016-10-21)

* Feature: Obey explicitly set HTTP protocol version for outgoing requests
  (#58, #59 by @WyriHaximus, @clue)

  ```php
  $request = new Request('GET', $url);
  $request = $request->withProtocolVersion(1.1);
  
  $browser->send($request)->then(…);
  ```

## 1.0.1 (2016-08-12)

* Fix: Explicitly define all minimum required package versions
  (#57 by @clue)

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
