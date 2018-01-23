<?php

namespace Clue\React\Buzz;

use Clue\React\Buzz\Io\Sender;
use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\MessageFactory;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectorInterface;

class Browser
{
    private $sender;
    private $messageFactory;
    private $baseUri = null;
    private $options = array();

    /**
     * Instantiate the Browser
     *
     * @param LoopInterface $loop
     * @param ConnectorInterface|null $connector [optional] Connector to use.
     *     Should be `null` in order to use default Connector.
     */
    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        $this->sender = Sender::createFromLoop($loop, $connector);
        $this->messageFactory = new MessageFactory();
    }

    /**
     * @param string|UriInterface $url URI for the request.
     * @param array $headers
     * @return PromiseInterface
     */
    public function get($url, array $headers = array())
    {
        return $this->send($this->messageFactory->request('GET', $url, $headers));
    }

    /**
     * @param string|UriInterface $url URI for the request.
     * @param array $headers
     * @param string $content
     * @return PromiseInterface
     */
    public function post($url, array $headers = array(), $content = '')
    {
        return $this->send($this->messageFactory->request('POST', $url, $headers, $content));
    }

    /**
     * @param string|UriInterface $url URI for the request.
     * @param array $headers
     * @return PromiseInterface
     */
    public function head($url, array $headers = array())
    {
        return $this->send($this->messageFactory->request('HEAD', $url, $headers));
    }

    /**
     * @param string|UriInterface $url URI for the request.
     * @param array $headers
     * @param string $content
     * @return PromiseInterface
     */
    public function patch($url, array $headers = array(), $content = '')
    {
        return $this->send($this->messageFactory->request('PATCH', $url , $headers, $content));
    }

    /**
     * @param string|UriInterface $url URI for the request.
     * @param array $headers
     * @param string $content
     * @return PromiseInterface
     */
    public function put($url, array $headers = array(), $content = '')
    {
        return $this->send($this->messageFactory->request('PUT', $url, $headers, $content));
    }

    /**
     * @param string|UriInterface $url URI for the request.
     * @param array $headers
     * @param string $content
     * @return PromiseInterface
     */
    public function delete($url, array $headers = array(), $content = '')
    {
        return $this->send($this->messageFactory->request('DELETE', $url, $headers, $content));
    }

    /**
     * @param string|UriInterface $url URI for the request.
     * @param array $fields
     * @param array $headers
     * @param string $method
     * @return PromiseInterface
     */
    public function submit($url, array $fields, $headers = array(), $method = 'POST')
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $content = http_build_query($fields);

        return $this->send($this->messageFactory->request($method, $url, $headers, $content));
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
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

    /**
     * @param array $options
     * @return self
     */
    public function withOptions(array $options)
    {
        $browser = clone $this;

        // merge all options, but remove those explicitly assigned a null value
        $browser->options = array_filter($options + $this->options, function ($value) {
            return ($value !== null);
        });

        return $browser;
    }
}
