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
        return $this->request('GET', $url, $headers);
    }

    public function post($url, $headers = array(), $content = '')
    {
        return $this->request('POST', $url, $headers, $content);
    }

    public function head($url, $headers = array())
    {
        return $this->request('HEAD', $url, $headers);
    }

    public function patch($url, $headers = array(), $content = '')
    {
        return $this->request('PATCH', $url , $headers, $content);
    }

    public function put($url, $headers = array(), $content = '')
    {
        return $this->request('PUT', $url, $headers, $content);
    }

    public function delete($url, $headers = array(), $content = '')
    {
        return $this->request('DELETE', $url, $headers, $content);
    }

    public function submit($url, array $fields, $headers = array(), $method = 'POST')
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $content = http_build_query($fields);

        return $this->request($method, $url, $headers, $content);
    }

    public function request($method, $url, $headers = array(), $content = null)
    {
        if (!($headers instanceof Headers)) {
            $headers = new Headers($headers);
        }
        if (!($content instanceof Body)) {
            $content = new Body($content);
        }

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

    public function withSender(Sender $sender)
    {
        $browser = clone $this;
        $browser->sender = $sender;

        return $browser;
    }
}
