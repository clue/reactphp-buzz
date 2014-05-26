<?php

namespace Clue\React\Buzz\Message;

class HeaderBag
{
    private $headers;

    public static function factory($headers)
    {
        if (!($headers instanceof self)) {
            $headers = new self($headers);
        }
        return $headers;
    }

    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    public function getHeaderValues($search)
    {
        $search = strtolower($search);

        $ret = array();

        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $search) {
                if (is_array($values)) {
                    foreach ($values as $value) {
                        $ret []= $value;
                    }
                } else {
                    $ret []= $values;
                }
            }
        }

        return $ret;
    }

    public function getHeaderValue($search)
    {
        return reset($this->getHeaderValues($search));
    }

    public function getAll()
    {
        return $this->headers;
    }
}
