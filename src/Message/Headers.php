<?php

namespace Clue\React\Buzz\Message;

class Headers
{
    private $headers;

    public function __construct(array $headers = array())
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
        $values = $this->getHeaderValues($search);

        return isset($values[0]) ? $values[0] : null;
    }

    public function getAll()
    {
        return $this->headers;
    }
}
