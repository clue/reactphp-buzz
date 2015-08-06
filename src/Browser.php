<?php

namespace Clue\React\Buzz;

use React\EventLoop\LoopInterface;
use Clue\React\Buzz\Message\Request;
use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\Body;
use Clue\React\Buzz\Message\Headers;
use Clue\React\Buzz\Io\Sender;
use Clue\React\Buzz\Message\Uri;
use Rize\UriTemplate;

class Browser
{
    private $sender;
    private $loop;
    private $uriTemplate;
    private $baseUri = null;
    private $options = array();

    public function __construct(LoopInterface $loop, Sender $sender = null, UriTemplate $uriTemplate = null)
    {
        if ($sender === null) {
            $sender = Sender::createFromLoop($loop);
        }
        if ($uriTemplate === null) {
            $uriTemplate = new UriTemplate();
        }
        $this->sender = $sender;
        $this->loop = $loop;
        $this->uriTemplate = $uriTemplate;
    }

    public function get($url, $headers = array())
    {
        return $this->send(new Request('GET', $this->resolve($url), $headers));
    }

    public function post($url, $headers = array(), $content = '')
    {
        return $this->send(new Request('POST', $this->resolve($url), $headers, $content));
    }

    public function head($url, $headers = array())
    {
        return $this->send(new Request('HEAD', $this->resolve($url), $headers));
    }

    public function patch($url, $headers = array(), $content = '')
    {
        return $this->send(new Request('PATCH', $this->resolve($url) , $headers, $content));
    }

    public function put($url, $headers = array(), $content = '')
    {
        return $this->send(new Request('PUT', $this->resolve($url), $headers, $content));
    }

    public function delete($url, $headers = array(), $content = '')
    {
        return $this->send(new Request('DELETE', $this->resolve($url), $headers, $content));
    }

    public function submit($url, array $fields, $headers = array(), $method = 'POST')
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $content = http_build_query($fields);

        return $this->send(new Request($method, $this->resolve($url), $headers, $content));
    }

    public function send(Request $request)
    {
        $transaction = new Transaction($request, $this->sender, $this->options);

        return $transaction->send();
    }

    /**
     * Returns an absolute URI by processing the given relative URI, possibly using URI template syntax (RFC 6570)
     *
     * You can either pass in a relative or absolute URI, which may or may not
     * contain any number of URI template placeholders.
     *
     * A relative URI can be given as a string value which may contain placeholders.
     * An absolute URI can be given as a string value which may contain placeholders.
     *
     * You can also pass in an `Uri` instance. By definition of this library,
     * an `Uri` instance is always absolute and can not contain any placeholders.
     * As such, it is safe to pass the result of this method as input to this method.
     *
     * @param string|Uri $uri        relative or absolute URI
     * @param array      $parameters (optional) parameters for URI template placeholders (RFC 6570)
     * @return Uri absolute URI
     * @see self::withBase()
     */
    public function resolve($uri, $parameters = array())
    {
        // not already an absolute `Uri` instance?
        if (!($uri instanceof Uri)) {
            // relative URIs should be prefixed with base URI
            if ($this->baseUri !== null) {
                $uri = $this->baseUri->expandBase($uri);
            }

            // replace all URI template placeholders (RFC 6570)
            $uri = $this->uriTemplate->expand($uri, $parameters);

            // ensure this is actually a valid, absolute URI instance
            $uri = new Uri($uri);
        }

        // ensure we're actually below the base URI
        if ($this->baseUri !== null) {
            $this->baseUri->assertBaseOf($uri);
        }

        return $uri;
    }

    /**
     * Creates a new Browser instance with the given absolute base URI
     *
     * This is mostly useful for use with the `resolve()` method.
     * Any relative URI passed to `resolve()` will simply be appended behind the given
     * `$baseUri`.
     *
     * @param string|Uri $baseUri absolute base URI
     * @return self
     * @see self::url()
     * @see self::withoutBase()
     */
    public function withBase($baseUri)
    {
        $browser = clone $this;
        $browser->baseUri = new Uri($baseUri);

        return $browser;
    }

    /**
     * Creates a new Browser instance *without* a base URL
     *
     * @return self
     * @see self::withBase()
     */
    public function withoutBase()
    {
        $browser = clone $this;
        $browser->baseUri = null;

        return $browser;
    }

    public function withOptions(array $options)
    {
        $browser = clone $this;

        // merge all options, but remove those explicitly assigned a null value
        $browser->options = array_filter($options + $this->options, function ($value) {
            return ($value !== null);
        });

        return $browser;
    }

    public function withSender(Sender $sender)
    {
        $browser = clone $this;
        $browser->sender = $sender;

        return $browser;
    }
}
