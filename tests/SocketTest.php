<?php

use Matasar\PhpTcp\Exception\SocketException;
use Matasar\PhpTcp\Socket\FSocket;
use Matasar\PhpTcp\Socket\SocketInterface;
use Matasar\PhpTcp\Socket\StreamSocket;
use PHPUnit\Framework\TestCase;

class SocketTest extends TestCase
{
    /**
     * @var resource|null
     */
    private $server;

    public function socketProvider(): array
    {
        return [
            'stream socket' => [new StreamSocket()],
            'fsocket' => [new FSocket()],
        ];
    }

    /**
     * @dataProvider socketProvider
     */
    public function testHostWithSchemeIsRejected(SocketInterface $socket)
    {
        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Host must not contain a transport scheme, got "udp://127.0.0.1"');

        $socket->connect('udp://127.0.0.1', '9999', 1);
    }

    /**
     * @dataProvider socketProvider
     */
    public function testPlainHostConnectsOverTcp(SocketInterface $socket)
    {
        $stream = $socket->connect('127.0.0.1', (string) $this->serverPort(), 1);

        $this->assertIsResource($stream);
        $this->assertStringStartsWith('tcp_socket', stream_get_meta_data($stream)['stream_type']);

        fclose($stream);
    }

    protected function tearDown(): void
    {
        if (null !== $this->server) {
            fclose($this->server);
            $this->server = null;
        }
    }

    private function serverPort(): int
    {
        $this->server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if (false === $this->server) {
            $this->markTestSkipped(sprintf('Unable to listen on 127.0.0.1: %s', $errorMessage));
        }

        return (int) explode(':', stream_socket_get_name($this->server, false))[1];
    }
}
