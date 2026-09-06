<?php

namespace Matasar\PhpTcp\Socket;

use Matasar\PhpTcp\Exception\SocketException;

class StreamSocket implements SocketInterface
{
    /**
     * @var int
     */
    private $blockingTimeout;

    /**
     * @var bool
     */
    private $blocking;

    /**
     * @param int $blockingTimeout Read timeout in sec., passed to stream_set_timeout()
     * @param bool|null $blocking Whether the stream blocks; null keeps the old rule, blocking when the timeout is positive
     */
    public function __construct(int $blockingTimeout = 1, ?bool $blocking = null)
    {
        $this->blockingTimeout = $blockingTimeout;
        $this->blocking = $blocking ?? $blockingTimeout > 0;
    }

    public function connect(string $host, string $port, ?float $timeout)
    {
        if (false !== strpos($host, '://')) {
            throw new SocketException(sprintf('Host must not contain a transport scheme, got "%s"', $host));
        }

        if (false !== strpos($host, ':') && '[' !== substr($host, 0, 1)) {
            // an IPv6 literal: bracket it so its colons do not run into the port
            $host = sprintf('[%s]', $host);
        }

        $address = sprintf('tcp://%s:%d', $host, $port);
        $stream = stream_socket_client($address, $errorCode, $errorMessage, $timeout);

        if (false === $stream) {
            throw new SocketException($errorMessage, $errorCode);
        }

        stream_set_blocking($stream, $this->blocking);
        stream_set_timeout($stream, $this->blockingTimeout);

        return $stream;
    }
}
