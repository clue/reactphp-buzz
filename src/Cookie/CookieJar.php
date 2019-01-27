<?php
namespace Clue\React\Buzz\Cookie;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * CookieJar is responsible for automatic handling HTTP cookies 
  */
class CookieJar
{
    /** @var CookieSet[] cookies array */
    private $cookies;

    /** @var string filename where the cookis will be stored */
    private $filename;


    /** @var bool indicates if we need throw exception or just ignore invalid cookies  */
    private $strictMode = false;

    /** @var bool indicates if we save session cookies in the cookie-file or not  */
    private $persistSessionCookies = false;

    /**
     * @param string $cookiesFilename Filename where the cookies are stored.
     */
    public function __construct($cookiesFilename = null)
    {
        $this->filename = $cookiesFilename;
        if ($cookiesFilename !== null && \file_exists($cookiesFilename)) {
            $this->load($cookiesFilename);
        } else {
            $this->cookies = array();
        }
    }

    public function __destruct()
    {
        $this->save();
    }

    /**
     * Load the cookies from $filename.
     * @param string $filename
     */
    public function load($filename)
    {
        if (!\file_exists($filename)) {
            throw new \RuntimeException("$filename does not exist");
        }

        $jsonContent = file_get_contents($filename);
        
        if ($jsonContent === false) {
            throw new \RuntimeException("Unable to load file $filename");
        }

        $this->cookies = array();
        if ($jsonContent === '') {
            return;
        }

        $cookies = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('json_decode error: ' . json_last_error_msg());
        }

        if (!is_array($cookies)) {
            throw new \RuntimeException("Invalid cookie file: $filename");
        }

        foreach ($cookies as $cookie) {
            $this->setCookie(new CookieSet($cookie));
        }
    }

    /**
     * Store all the cookies to $filename.
     * @param string $filename
     */
    public function save($filename = null)
    {
        if ($filename === null) {
            if ($this->filename === null) {
                if ($this->strictMode) {
                    throw new \RuntimeException("Missing cookie filename"); 
                }
                return;
            }
            $filename = $this->filename;
        }

        //
        $content = array();
        foreach ($this->cookies as $domain => $cookies) {
            foreach ($cookies as $cookie) {
                if (!$cookie->isExpired()) {
                    if ($this->persistSessionCookies || !$cookie->isSessionCookie()) {
                        $content[] = $cookie->toArray();
                    }
                }
            }
        }
        \file_put_contents($this->filename, \json_encode($content));
    }

    /**
     * Add a cookie in the list. If already exists a cookie with same name, domain and path
     * it will be overridden.
     * @param CookieSet $cookie
     */
    public function setCookie(CookieSet $cookie)
    {
        if (!$cookie->isValid()) {
            if($this->strictMode) {
                throw new \InvalidArgumentException("CookieSet invalid");
            }
            return;
        }

        $domain = ltrim($cookie->getDomain(), '.');
        if (empty($this->cookies[$domain])) {
            $this->cookies[$domain] = array();
        }
        foreach ($this->cookies[$domain] as $idx => $c) {
            if ($c->getName() === $cookie->getName() && $c->getPath() === $cookie->getPath()) {
                $this->cookies[$domain][$idx] = $cookie;
                return;
            }
        }
        $this->cookies[$domain][] = $cookie;
    }

    /**
     * @internal
     */
    private function matchPath($cookie, $path)
    {
        $cookiePath = $cookie->getPath();
        if ($cookiePath === '/' || $cookiePath == $requestPath) {
            return true;
        }
        if (strpos($requestPath, $cookiePath) !== 0) {
            return false;
        }
        $cookiePathLen = strlen($cookiePath);
        return $cookiePath[$cookiePathLen - 1] === '/' || $requestPath[$cookiePathLen] === '/';
    }

    /**
     * Return all cookies that match with $uri. Expired cookies are ignored.
     * @param UriInterface $uri
     * @return array
     */
    public function getCookiesFromUri(UriInterface $uri)
    {
        $path = $uri->getPath();
        $https = $uri->getScheme() === 'https';
        $parts = explode('.', \ltrim($uri->getHost(), '.'));

        $cookies = array();

        $domain = '';
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $domain = ($domain === '') ? $parts[$i] : $parts[$i] . '.' . $domain;

            if (!isset($this->cookies[$domain])) {
                continue;
            }

            foreach ($this->cookies[$domain] as $j => $cookie) {
                // auto-remove expired cookies
                if ($cookie->isExpired()) {
                    unset($this->cookies[$domain][$j]);
                } else {
                    if ($this->matchPath($cookie, $path) && (!$cookie->getSecure() || $https)) {
                        $cookies[$cookie->getName()] = $cookie;
                    }
                }
            }
        }
        return $cookies;
    }

    /**
     * @internal
     * Handler that injects the cookies in every request made by Browser object.
     */
    public function onRequest(RequestInterface $request)
    {
        $cookies = array();
        foreach ($this->getCookiesFromUri($request->getUri()) as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
        // to save cpu-usage
        if(empty($cookies)){
            return $request;
        }

        // we replace the cookies if the request contains custom cookies
        $cookieHeaders = $request->getHeader('Cookie');
        if (!empty($cookieHeaders)) {
            foreach ($cookieHeaders as $headerLine) {
                $theseCookies = explode('; ', $headerLine);
                foreach ($theseCookies as $cookieEntry) {
                    $cookieParts = explode('=', $cookieEntry, 2);
                    if (count($cookieParts) == 2) {
                        // We have the name and value of the cookie!
                        $cookies[$cookieParts[0]] = $cookieParts[1];
                    } else {
                        // Unable to find an equals sign, just re-use this
                        // this is invalid. But we store it to send on request just 'as-is'
                        $cookies[$cookieEntry] = null;
                    }
                }
            }
        }

        $values = array();
        foreach ($cookies as $name => $value) {
            if ($value === null) {
                // If the cookie was sent without a value, just re-use 'as-is'
                $values[] = $name;
            } else {
                $values[] = $name.'='.$value;
            }
        }

        $cookieHeader = implode('; ', $values);
        return $request->withHeader('Cookie', $cookieHeader);
    }

    /**
     * @internal
     * Handler that listen responses to capture new cookies or cookies-value update
     */
    public function onResponse(ResponseInterface $response, RequestInterface $request){
        $cookieHeaders = $response->getHeader('Set-Cookie');
        $defaultHost = $request->getUri()->getHost();

        foreach ($cookieHeaders as $line) {
            $cookie = CookieSet::fromString($line);

            $domain = $cookie->getDomain();
            if (empty($domain)) {
                $cookie->setDomain($defaultHost);
            }
            $this->setCookie($cookie);
        }
        return $response;
    }

    /**
     * Define whether or not session cookies will be stored in the cookie file
     * @param bool $persist
     */
    public function setPersistSessionCookies($persist = false)
    {
        $this->persistSessionCookies = $persist;
    }

    /**
     * Define whether or not session cookies will be stored in the cookie file
     * @param string $filename
     */
    public function setCookieFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Set whether or not this cookieJar will executes in strict-mode: 
     * In strict-mode, an exception will be thrown if an invalid cookie is found.
     * @param bool $strict
     */
    public function setStrictMode($strict = true)
    {
        $this->strictMode = $strict;
    }
}
