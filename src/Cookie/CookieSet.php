<?php
namespace Clue\React\Buzz\Cookie;

/**
 * CookieSet object
 */
class CookieSet
{
    /** @var array */
    private static $properties = [
        'Name'     => null,
        'Value'    => null,
        'Domain'   => null,
        'Path'     => '/',
        'Max-Age'  => null,
        'Expires'  => null,
        'Secure'   => false,
        'Discard'  => false,
        'HttpOnly' => false
    ];

    /** @var array Cookie data */
    private $data;

    public static function fromString($cookie)
    {
        $data = self::$properties;
        $pieces = explode(';', $cookie);

        // if the value is missing
        if (empty($pieces[0]) || !strpos($pieces[0], '=')) {
            return new self($data);
        }

        // Add the cookie pieces into the parsed data array
        foreach ($pieces as $part) {
            $cookieParts = explode('=', $part, 2);
            $key = trim($cookieParts[0]);
            $value = isset($cookieParts[1]) ? trim($cookieParts[1]) : true;

            if (empty($data['Name'])) {
                $data['Name'] = $key;
                $data['Value'] = $value;
            } else {
                $key = strcasecmp($key, 'HttpOnly') === 0 ? 'HttpOnly' : ucwords($key, ' -');
                if (array_key_exists($key, self::$properties)) {
                    $data[$key] = $value;
                }
            }
        }

        return new self($data);
    }

    /**
     * @param array $data Array of cookie data provided by a Cookie parser
     */
    public function __construct(array $data = array())
    {
        $this->data = array();
        foreach (CookieSet::$properties as $property => $default) {
            $this->data[$property] = isset($data[$property]) ? $data[$property] : $default;
        }
    }

     /**
     * Check if the cookie is valid
     *
     * @return bool
     */
    public function isValid()
    {
        if ((empty($this->data['Name']) && $this->data['Name'] != '0') ||
            (empty($this->data['Value']) && $this->data['Value'] != '0') ||
            (empty($this->data['Domain']) && $this->data['Domain'] != '0')) {
            return false;
        }

        // Not valid ASCI characters in cookie-name
        if (preg_match(
                '/[\x00-\x20\x22\x28-\x29\x2c\x2f\x3a-\x40\x5c\x7b\x7d\x7f]/',
                $this->data['Name']
           )) {
            return false;
        }
        return true;
    }

    /**
     * Get the string representing the cookie
     *
     * @return string
     */
    public function __toString()
    {
        $str = $this->data['Name'] . '=' . $this->data['Value'];
        if (!empty($this->data['Expires'])) {
            $str .= '; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $this->data['Expires']);
        }
        foreach ($this->data as $k => $v) {
            if ($k === 'Name' || $k === 'Value' || $k === 'Expires' ||
                $v === false || $v === null) {
                continue;
            }
            $str .= '; ' . ($v === true ? $k : "{$k}={$v}");
        }

        return $str;
    }

    /**
     * Get the cookie properties as array
     *
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Get the cookie name
     *
     * @return string
     */
    public function getName()
    {
        return $this->data['Name'];
    }

    /**
     * Set the cookie name
     *
     * @param string $name Cookie name
     */
    public function setName($name)
    {
        $this->data['Name'] = $name;
    }

    /**
     * Get the cookie value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->data['Value'];
    }

    /**
     * Set the cookie value
     *
     * @param string $value Cookie value
     */
    public function setValue($value)
    {
        $this->data['Value'] = $value;
    }

    /**
     * Get the domain
     *
     * @return string|null
     */
    public function getDomain()
    {
        return $this->data['Domain'];
    }

    /**
     * Set the domain of the cookie
     *
     * @param string $domain
     */
    public function setDomain($domain)
    {
        $this->data['Domain'] = $domain;
    }

    /**
     * Get the path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->data['Path'];
    }

    /**
     * Set the path of the cookie
     *
     * @param string $path Path of the cookie
     */
    public function setPath($path)
    {
        $this->data['Path'] = $path;
    }

    /**
     * Maximum lifetime of the cookie in seconds
     *
     * @return int|null
     */
    public function getMaxAge()
    {
        return $this->data['Max-Age'];
    }

    public function setMaxAge($maxAge)
    {
        $this->data['Max-Age'] = $maxAge;
    }

    public function getExpires()
    {
        return $this->data['Expires'];
    }

    /**
     * Set the unix timestamp for which the cookie will expire
     *
     * @param int $timestamp Unix timestamp
     */
    public function setExpires($timestamp)
    {
        $this->data['Expires'] = is_numeric($timestamp) ? (int) $timestamp : strtotime($timestamp);
    }

    /**
     * Get whether or not this is a secure cookie
     *
     * @return null|bool
     */
    public function getSecure()
    {
        return $this->data['Secure'];
    }

    /**
     * Set whether or not the cookie is secure
     *
     * @param bool $secure Set to true or false if secure
     */
    public function setSecure($secure)
    {
        $this->data['Secure'] = $secure;
    }

    /**
     * Get whether or not discard this cookie at the end of session.
     *
     * @return null|bool
     */
    public function getDiscard()
    {
        return $this->data['Discard'];
    }

    /**
     * Get whether or not this is a session cookie
     *
     * @return null|bool
     */
    public function isSessionCookie()
    {
        return $this->data['Discard'] || $this->data['Expires'] === null;
    }

    /**
     * Set whether or not this is a session cookie
     *
     * @param bool $discard Set to true or false if this is a session cookie
     */
    public function setDiscard($discard)
    {
        $this->data['Discard'] = $discard;
    }

    /**
     * Get whether or not this is an HTTP only cookie
     *
     * @return bool
     */
    public function getHttpOnly()
    {
        return $this->data['HttpOnly'];
    }

    /**
     * Set whether or not this is an HTTP only cookie
     *
     * @param bool $httpOnly Set to true or false if this is HTTP only
     */
    public function setHttpOnly($httpOnly)
    {
        $this->data['HttpOnly'] = $httpOnly;
    }

    /**
     * Check if the cookie is expired
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->getExpires() !== null && $this->getExpires() < time();
    }
}
