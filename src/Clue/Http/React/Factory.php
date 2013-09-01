<?php

namespace Clue\Http\React;

use React\EventLoop\LoopInterface;
use Clue\Http\React\Client\Browser;
use React\Dns\Resolver\Resolver;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\HttpClient\Factory as HttpFactory;

class Factory
{
    private $loop;
    private $resolver = null;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function setResolver($resolver)
    {
        if (!($resolver instanceof Resolver)) {
            $dnsResolverFactory = new ResolverFactory();
            $resolver = $dnsResolverFactory->createCached($resolver, $this->loop);
        }
        $this->resolver = $resolver;
    }

    public function createClient()
    {
        return new Browser($this->loop, $this->createHttpClient());
    }

    public function createHttpClient()
    {
        if ($this->resolver === null) {
            // default to google's public DNS server if nothing else is set
            $this->setResolver('8.8.8.8');
        }

        $factory = new HttpFactory();
        return $factory->create($this->loop, $this->resolver);
    }
}