<?php

namespace Clue\React\Buzz;

use React\EventLoop\LoopInterface;
use Clue\React\Buzz\Message\Request;
use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\Body;
use Clue\React\Buzz\Message\Headers;
use Clue\React\Buzz\Io\Sender;
use Clue\React\Buzz\Message\Uri;

class Browser
{
    private $sender;
    private $loop;
    private $baseUri = null;
    private $options = array();

    public function __construct(LoopInterface $loop, Sender $sender = null)
    {
        if ($sender === null) {
            $sender = Sender::createFromLoop($loop);
        }
        $this->sender = $sender;
        $this->loop = $loop;
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
     * Returns an absolute URI by processing the given relative URI
     *
     * @param string|Uri $uri relative or absolute URI
     * @return Uri absolute URI
     * @see self::withBase()
     */
    public function resolve($uri)
    {
        if ($this->baseUri !== null) {
            return $this->baseUri->expandBase($uri);
        }

        return new Uri($uri);
    }

    /**
     * Creates a new Browser instance with the given absolute base URI
     *
     * This is mostly useful for use with the `resolve()` method.
     * Any relative URI passed to `uri()` will simply be appended behind the given
     * `$baseUrl`.
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
