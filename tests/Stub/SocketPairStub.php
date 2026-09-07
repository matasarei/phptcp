<?php

namespace Stub;

use Matasar\PhpTcp\Socket\SocketInterface;

/**
 * Hands the client one end of a real socket pair, so reads go through the same code a live
 * connection does - including returning false from fread() on a read timeout, which is what a
 * stream wrapper cannot reproduce.
 */
class SocketPairStub implements SocketInterface
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    public function connect(string $host, string $port, ?float $timeout)
    {
        stream_set_blocking($this->stream, true);
        stream_set_timeout($this->stream, 0, 200000);

        return $this->stream;
    }
}
