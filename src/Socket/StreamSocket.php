<?php

namespace Matasar\PhpTcp\Socket;

use Matasar\PhpTcp\Exception\SocketException;

class StreamSocket implements SocketInterface
{
    private $blockingTimeout;

    /**
     * @param int $blockingTimeout Blocking timeout in sec.
     */
    public function __construct($blockingTimeout = 1)
    {
        $this->blockingTimeout = $blockingTimeout;
    }

    public function connect(string $host, string $port, ?float $timeout)
    {
        $address = sprintf('%s:%d', $host, $port);
        $stream = stream_socket_client($address, $errorCode, $errorMessage, $timeout);

        if (false === $stream) {
            throw new SocketException($errorMessage, $errorCode);
        }

        stream_set_blocking($stream, $this->blockingTimeout > 0);
        stream_set_timeout($stream, $this->blockingTimeout);

        return $stream;
    }
}
