<?php

namespace Clue\React\Buzz;

use React\EventLoop\LoopInterface;
use Psr\Http\Message\RequestInterface;
use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\Body;
use Clue\React\Buzz\Message\Headers;
use Clue\React\Buzz\Io\Sender;
use Psr\Http\Message\UriInterface;
use Rize\UriTemplate;
use Clue\React\Buzz\Message\MessageFactory;

class Browser
{
    private $sender;
    private $loop;
    private $uriTemplate;
    private $messageFactory;
    private $baseUri = null;
    private $options = array();

    public function __construct(LoopInterface $loop, Sender $sender = null, UriTemplate $uriTemplate = null, MessageFactory $messageFactory = null)
    {
        if ($sender === null) {
            $sender = Sender::createFromLoop($loop);
        }
        if ($uriTemplate === null) {
            $uriTemplate = new UriTemplate();
        }
        if ($messageFactory === null) {
            $messageFactory = new MessageFactory();
        }
        $this->sender = $sender;
        $this->loop = $loop;
        $this->uriTemplate = $uriTemplate;
        $this->messageFactory = $messageFactory;
    }

    public function get($url, $headers = array())
    {
        return $this->send($this->messageFactory->request('GET', $url, $headers));
    }

    public function post($url, $headers = array(), $content = '')
    {
        return $this->send($this->messageFactory->request('POST', $url, $headers, $content));
    }

    public function head($url, $headers = array())
    {
        return $this->send($this->messageFactory->request('HEAD', $url, $headers));
    }

    public function patch($url, $headers = array(), $content = '')
    {
        return $this->send($this->messageFactory->request('PATCH', $url , $headers, $content));
    }

    public function put($url, $headers = array(), $content = '')
    {
        return $this->send($this->messageFactory->request('PUT', $url, $headers, $content));
    }

    public function delete($url, $headers = array(), $content = '')
    {
        return $this->send($this->messageFactory->request('DELETE', $url, $headers, $content));
    }

    public function submit($url, array $fields, $headers = array(), $method = 'POST')
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $content = http_build_query($fields);

        return $this->send($this->messageFactory->request($method, $url, $headers, $content));
    }

    public function send(RequestInterface $request)
    {
        if ($this->baseUri !== null) {
            // ensure we're actually below the base URI
            $request = $request->withUri($this->messageFactory->expandBase($request->getUri(), $this->baseUri));
        }

        $transaction = new Transaction($request, $this->sender, $this->options, $this->messageFactory);

        return $transaction->send();
    }

    /**
     * Resolves a relative or absolute URI by processing URI template placeholders according to RFC 6570
     *
     * You can either pass in a relative or absolute URI, which may or may not
     * contain any number of URI template placeholders.
     *
     * @param string $uri        relative or absolute URI with URI template placeholders (RFC 6570)
     * @param array  $parameters parameters for URI template placeholders
     * @return string relative or absolute URI with URI template placeholders resolved according to RFC 6570
     */
    public function resolve($uri, array $parameters)
    {
        // only string URIs may contain URI template placeholders (RFC 6570)
        return $this->uriTemplate->expand($uri, $parameters);
    }

    /**
     * Creates a new Browser instance with the given absolute base URI
     *
     * This is mostly useful for using (RESTful) HTTP APIs.
     * Any relative URI passed to any of the request methods will simply be
     * appended behind the given `$baseUri`.
     *
     * By definition of this library, a given base URI MUST always absolute and
     * can not contain any placeholders.
     *
     * @param string|UriInterface $baseUri absolute base URI
     * @return self
     * @throws InvalidArgumentException if the given $baseUri is not a valid absolute URI
     * @see self::withoutBase()
     */
    public function withBase($baseUri)
    {
        $browser = clone $this;
        $browser->baseUri = $this->messageFactory->uri($baseUri);

        if ($browser->baseUri->getScheme() === '' || $browser->baseUri->getHost() === '') {
            throw new \InvalidArgumentException('Base URI must be absolute');
        }

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
