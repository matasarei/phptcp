<?php

namespace Matasar\PhpTcp;

use Matasar\PhpTcp\Exception\ConnectionException;
use Matasar\PhpTcp\Exception\RequestException;
use Matasar\PhpTcp\Exception\SocketException;
use Matasar\PhpTcp\Socket\SocketInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client implements LoggerAwareInterface
{
    /**
     * Default poll interval: 1 ms (1000 microseconds)
     */
    const DEFAULT_POLL_INTERVAL = 1000;

    /**
     * @deprecated Use DEFAULT_POLL_INTERVAL instead
     */
    const DEFAULT_CONNECTION_LAG = self::DEFAULT_POLL_INTERVAL;

    /**
     * Bytes per chunk; matches the size of PHP's internal stream read buffer
     */
    const DEFAULT_CHUNK_SIZE = 8192;

    /**
     * Default timeout in sec
     */
    const DEFAULT_TIMEOUT = 2;

    /**
     * @var resource|null
     */
    private $stream;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * @var int
     */
    private $pollInterval;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SocketInterface
     */
    private $socketInterface;

    /**
     * @var string|null
     */
    private $delimiter;

    public function __construct(string $host, int $port, SocketInterface $socketInterface)
    {
        $this->host = $host;
        $this->port = $port;
        $this->socketInterface = $socketInterface;

        $this->stream = null;
        $this->logger = new NullLogger();
        $this->chunkSize = self::DEFAULT_CHUNK_SIZE;
        $this->pollInterval = self::DEFAULT_POLL_INTERVAL;
        $this->delimiter = null;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param int $chunkSize Chunk length in bytes
     */
    public function setChunkSize(int $chunkSize): void
    {
        $this->chunkSize = $chunkSize;
    }

    /**
     * @param int $pollInterval Pause between data availability checks, in microseconds
     */
    public function setPollInterval(int $pollInterval): void
    {
        $this->pollInterval = $pollInterval;
    }

    /**
     * @deprecated Use setPollInterval() instead
     *
     * @param int $connectionLag Poll interval in microseconds
     */
    public function setConnectionLag(int $connectionLag): void
    {
        $this->setPollInterval($connectionLag);
    }

    /**
     * When a delimiter is set, a response is considered complete as soon as it ends with the delimiter
     * (e.g. "\n" for line-based protocols), instead of waiting for a silent interval on the stream.
     * The delimiter is kept at the end of the response data.
     *
     * @param string|null $delimiter End-of-response marker, or null to detect the end by a pause in the data flow
     */
    public function setDelimiter(?string $delimiter): void
    {
        $this->delimiter = '' === $delimiter ? null : $delimiter;
    }

    public function isConnected(): bool
    {
        return null !== $this->stream;
    }

    /**
     * @param int $connectionTimeout Connection timeout in sec.
     *
     * @return Response
     *
     * @throws ConnectionException
     */
    public function connect($connectionTimeout = self::DEFAULT_TIMEOUT): Response
    {
        if ($this->isConnected()) {
            throw new ConnectionException("Already connected");
        }

        $this->logger->debug(sprintf('TCP: Connecting to %s:%s...', $this->host, $this->port));

        try {
            $stream = $this->socketInterface->connect($this->host, $this->port, $connectionTimeout);
        } catch (SocketException $exception) {
            throw new ConnectionException('Failed to connect', 0, $exception);
        }

        $this->stream = $stream;

        try {
            return new Response($this->read($connectionTimeout));
        } catch (RequestException $exception) {
            $this->logger->debug('TCP: ' . $exception->getMessage());

            return new Response('');
        }
    }

    public function disconnect(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * @param RequestInterface $request
     *
     * @return Response
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function request(RequestInterface $request): Response
    {
        if (!$this->isConnected()) {
            throw new ConnectionException("Not connected");
        }

        $this->logger->debug(sprintf('TCP: Sending a request to %s...', $this->host), ['request' => $request]);
        $this->write($request->getBody() . "\r\n");

        return new Response($this->read($request->getTimeout()));
    }

    /**
     * @param string $data
     *
     * @throws ConnectionException
     */
    private function write(string $data): void
    {
        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $bytes = fwrite($this->stream, substr($data, $written));

            if (false === $bytes || 0 === $bytes) {
                $this->disconnect();

                throw new ConnectionException('Request failed, unable to write to the stream.');
            }

            $written += $bytes;
        }
    }

    /**
     * @param int $timeout
     *
     * @return string
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    private function read(int $timeout): string
    {
        $data = $this->wait($timeout);
        $timeStart = microtime(true);

        while (!$this->isComplete($data)) {
            $chunk = fread($this->stream, $this->chunkSize);

            if (false === $chunk) {
                $this->disconnect();

                throw new ConnectionException('Request failed, broken connection.');
            }

            if ('' !== $chunk) {
                $data .= $chunk;

                continue;
            }

            if (feof($this->stream)) {
                break;
            }

            if (null === $this->delimiter) {
                // no delimiter to look for: a pause in the data flow marks the end of the response
                break;
            }

            if ((microtime(true) - $timeStart) > $timeout) {
                $this->disconnect();

                throw new RequestException('Request timeout, incomplete response.');
            }

            usleep($this->pollInterval);
        }

        $timePassed = (microtime(true) - $timeStart);
        $this->logger->debug(sprintf('TCP: Data transfer took %.5f sec.', $timePassed));

        return $data;
    }

    private function isComplete(string $data): bool
    {
        if (null === $this->delimiter) {
            return false;
        }

        return substr($data, -strlen($this->delimiter)) === $this->delimiter;
    }

    /**
     * @param int $timeout
     *
     * @return string Response start (first char)
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    private function wait(int $timeout): string
    {
        $timeStart = microtime(true);
        $timePassed = 0;

        while (($response = fread($this->stream, 1)) === '') {
            if (feof($this->stream)) {
                $this->disconnect();

                throw new ConnectionException('Request failed, connection closed by peer.');
            }

            $timePassed = (microtime(true) - $timeStart);

            if ($timePassed > $timeout) {
                $this->disconnect();

                throw new RequestException('Request timeout \ no response.');
            }

            usleep($this->pollInterval);
        }

        if ($response === false) {
            $this->disconnect();

            throw new ConnectionException('Request failed, broken connection.');
        }

        $this->logger->debug(
            sprintf(
                'TCP: Request took %.5f sec.',
                $timePassed === 0 ? (microtime(true) - $timeStart) : $timePassed
            )
        );

        return $response;
    }
}
