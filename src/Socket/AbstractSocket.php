<?php

namespace Matasar\PhpTcp\Socket;

use Matasar\PhpTcp\Exception\SocketException;

/**
 * Shared plumbing for the built-in transports: the host guard, the blocking settings and the
 * error handling. A subclass supplies only the call that opens the stream.
 *
 * @internal Not part of the published API. A custom transport implements SocketInterface, which
 *           is the one-method contract this library promises to keep.
 */
abstract class AbstractSocket implements SocketInterface
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

        $stream = $this->open($host, $port, $timeout, $errorCode, $errorMessage);

        if (false === $stream) {
            throw new SocketException($errorMessage, $errorCode);
        }

        stream_set_blocking($stream, $this->blocking);
        stream_set_timeout($stream, $this->blockingTimeout);

        return $stream;
    }

    /**
     * Opens the stream. The host is already guarded and bracketed.
     *
     * @param int|null $errorCode
     * @param string|null $errorMessage
     *
     * @return resource|false
     */
    abstract protected function open(string $host, string $port, ?float $timeout, &$errorCode, &$errorMessage);
}
