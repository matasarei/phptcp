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
     * Default value 1 ms (1000 microseconds)
     */
    const DEFAULT_CONNECTION_LAG = 1000;

    /**
     * Bytes per chunk
     */
    const DEFAULT_CHUNK_SIZE = 1024;

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
    private $connectionLag;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SocketInterface
     */
    private $socketInterface;

    public function __construct(string $host, int $port, SocketInterface $socketInterface)
    {
        $this->host = $host;
        $this->port = $port;
        $this->socketInterface = $socketInterface;

        $this->stream = null;
        $this->logger = new NullLogger();
        $this->chunkSize = self::DEFAULT_CHUNK_SIZE;
        $this->connectionLag = self::DEFAULT_CONNECTION_LAG;
    }

    public function setLogger(LoggerInterface $logger)
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
     * @param int $connectionLag Connection lag in microseconds
     */
    public function setConnectionLag(int $connectionLag): void
    {
        $this->connectionLag = $connectionLag;
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
        fwrite($this->stream, $request->getBody() . "\r\n");

        return new Response($this->read($request->getTimeout()));
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

        while (($chunk = fread($this->stream, $this->chunkSize)) !== '') {
            $data .= $chunk;

            usleep($this->connectionLag);
        }

        $timePassed = (microtime(true) - $timeStart);
        $this->logger->debug(sprintf('TCP: Data transfer took %.5f sec.', $timePassed));

        return $data;
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
            $timePassed = (microtime(true) - $timeStart);

            if ($timePassed > $timeout) {
                $this->disconnect();

                throw new RequestException('Request timeout \ no response.');
            }

            usleep($this->connectionLag);
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
