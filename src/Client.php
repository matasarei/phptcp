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
     * Largest response accepted by default: 8 MiB
     */
    const DEFAULT_MAX_RESPONSE_SIZE = 8388608;

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

    /**
     * @var int|null
     */
    private $maxResponseSize;

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
        $this->maxResponseSize = self::DEFAULT_MAX_RESPONSE_SIZE;
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
     * A peer that keeps sending would otherwise be read until the process runs out of memory.
     *
     * @param int|null $maxResponseSize Largest response accepted, in bytes, or null for no limit
     */
    public function setMaxResponseSize(?int $maxResponseSize): void
    {
        $this->maxResponseSize = $maxResponseSize;
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
     * @param float $connectionTimeout Connection timeout in sec., fractions allowed
     *
     * @return Response
     *
     * @throws ConnectionException
     */
    public function connect(float $connectionTimeout = self::DEFAULT_TIMEOUT): Response
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

        $this->logger->debug(
            sprintf('TCP: Sending a request to %s...', $this->host),
            ['length' => strlen($request->getBody())]
        );
        $this->write($request->getBody() . "\r\n", $request->getTimeout());

        return new Response($this->read($request->getTimeout()));
    }

    /**
     * @param string $data
     * @param int $timeout Send timeout in seconds
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    private function write(string $data, int $timeout): void
    {
        $length = strlen($data);
        $written = 0;
        $timeStart = microtime(true);

        while ($written < $length) {
            $bytes = fwrite($this->stream, substr($data, $written));

            if (false === $bytes) {
                $this->disconnect();

                throw new ConnectionException('Request failed, unable to write to the stream.');
            }

            if (0 === $bytes) {
                // the send buffer may be full (non-blocking mode); retry until the timeout
                if ((microtime(true) - $timeStart) > $timeout) {
                    $this->disconnect();

                    throw new RequestException('Request timeout, unable to send the request.');
                }

                usleep($this->pollInterval);

                continue;
            }

            $written += $bytes;
        }
    }

    /**
     * @param float $timeout Read timeout in sec., fractions allowed
     *
     * @return string
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    private function read(float $timeout): string
    {
        $timeStart = microtime(true);
        $data = $this->wait($timeout);

        while (!$this->isComplete($data)) {
            // checked on every iteration, so a peer that never pauses cannot hold the loop open
            if ((microtime(true) - $timeStart) > $timeout) {
                $this->disconnect();

                throw new RequestException('Request timeout, incomplete response.');
            }

            $chunk = fread($this->stream, $this->chunkSize);

            if (false === $chunk) {
                $this->disconnect();

                throw new ConnectionException('Request failed, broken connection.');
            }

            if ('' !== $chunk) {
                $data .= $chunk;

                if (null !== $this->maxResponseSize && strlen($data) > $this->maxResponseSize) {
                    $this->disconnect();

                    throw new RequestException(
                        sprintf('Response too large, over %d bytes.', $this->maxResponseSize)
                    );
                }

                continue;
            }

            if (feof($this->stream)) {
                if (null !== $this->delimiter) {
                    $this->disconnect();

                    throw new ConnectionException('Request failed, connection closed before the response was completed.');
                }

                break;
            }

            if (null === $this->delimiter) {
                // no delimiter to look for: a pause in the data flow marks the end of the response
                break;
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
     * @param float $timeout Wait timeout in sec., fractions allowed
     *
     * @return string Response start (first char)
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    private function wait(float $timeout): string
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

                throw new RequestException('Request timeout, no response.');
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
