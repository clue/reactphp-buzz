<?php

namespace Clue\Http\React\Client\Message\Response;

use React\Stream\Stream;

class DownloadResponse extends AbstractResponseDecorator
{
    private $progress = 0;
    private $targetStream;

    public function __construct(ResponseStream $response, Stream $targetStream)
    {
        parent::__construct($response);

        $this->targetStream = $targetStream;

        $progress =& $this->progress;
        $response->on('data', function ($data) use (&$progress) {
            $progress += strlen($data);
        });
    }

    public function getBytesDownloaded()
    {
        return $this->progress;
    }

    public function getBytesTotal()
    {
        return $this->decorated->getHeader('Content-Length');
    }

    public function getTargetStream()
    {
        return $this->targetStream;
    }
}
