<?php

namespace Clue\React\Buzz;

use React\EventLoop\LoopInterface;
use Clue\React\Buzz\Message\Request;
use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\Body;
use Clue\React\Buzz\Message\Headers;
use Clue\React\Buzz\Io\Sender;

class Browser
{
    private $sender;
    private $loop;
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
        return $this->send(new Request('GET', $url, $headers));
    }

    public function post($url, $headers = array(), $content = '')
    {
        return $this->send(new Request('POST', $url, $headers, $content));
    }

    public function head($url, $headers = array())
    {
        return $this->send(new Request('HEAD', $url, $headers));
    }

    public function patch($url, $headers = array(), $content = '')
    {
        return $this->send(new Request('PATCH', $url , $headers, $content));
    }

    public function put($url, $headers = array(), $content = '')
    {
        return $this->send(new Request('PUT', $url, $headers, $content));
    }

    public function delete($url, $headers = array(), $content = '')
    {
        return $this->send(new Request('DELETE', $url, $headers, $content));
    }

    public function submit($url, array $fields, $headers = array(), $method = 'POST')
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $content = http_build_query($fields);

        return $this->send(new Request($method, $url, $headers, $content));
    }

    public function send(Request $request)
    {
        $transaction = new Transaction($request, $this->sender, $this->options);

        return $transaction->send();
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
}
