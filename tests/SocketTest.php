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

    /**
     * @dataProvider socketProvider
     */
    public function testIpv6HostConnects(SocketInterface $socket)
    {
        $stream = $socket->connect('::1', (string) $this->serverPort('tcp://[::1]:0'), 1);

        $this->assertIsResource($stream);
        $this->assertStringStartsWith('tcp_socket', stream_get_meta_data($stream)['stream_type']);

        fclose($stream);
    }

    public function socketClassProvider(): array
    {
        return [
            'stream socket' => [StreamSocket::class],
            'fsocket' => [FSocket::class],
        ];
    }

    /**
     * @dataProvider socketClassProvider
     */
    public function testTimeoutDoesNotDecideBlocking(string $class)
    {
        $port = (string) $this->serverPort();

        $blocking = (new $class(5))->connect('127.0.0.1', $port, 1);
        $this->assertTrue(stream_get_meta_data($blocking)['blocked']);
        fclose($blocking);

        $nonBlocking = (new $class(5, false))->connect('127.0.0.1', $port, 1);
        $this->assertFalse(stream_get_meta_data($nonBlocking)['blocked']);
        fclose($nonBlocking);
    }

    protected function tearDown(): void
    {
        if (null !== $this->server) {
            fclose($this->server);
            $this->server = null;
        }
    }

    private function serverPort(string $address = 'tcp://127.0.0.1:0'): int
    {
        $this->server = @stream_socket_server($address, $errorCode, $errorMessage);

        if (false === $this->server) {
            $this->markTestSkipped(sprintf('Unable to listen on %s: %s', $address, $errorMessage));
        }

        // an IPv6 name is "[::1]:54321", so take what follows the last colon
        return (int) substr(strrchr(stream_socket_get_name($this->server, false), ':'), 1);
    }
}
