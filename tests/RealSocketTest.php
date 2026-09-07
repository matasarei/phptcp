<?php

use Matasar\PhpTcp\Client;
use Matasar\PhpTcp\Request;
use PHPUnit\Framework\TestCase;
use Stub\SocketPairStub;

/**
 * The rest of the suite drives Client through a php:// stream wrapper, whose read() returns an
 * empty string when there is nothing to read. A real socket that hits its read timeout returns
 * false from PHP 8.1 on, so these tests use a socket pair instead.
 */
class RealSocketTest extends TestCase
{
    /**
     * @var resource[]
     */
    private $pair = [];

    public function testGreetingIsReadFromARealSocket()
    {
        [$peer, $client] = $this->socketPair();

        fwrite($peer, "greeting\n");

        $this->assertEquals("greeting\n", $this->client($client)->connect()->getData());
    }

    public function testResponseIsReadFromARealSocketWithoutADelimiter()
    {
        [$peer, $client] = $this->socketPair();

        fwrite($peer, "greeting\n");

        $tcp = $this->client($client);
        $tcp->connect();

        fwrite($peer, "{\"result\":\"ok\"}\n");

        $this->assertEquals("{\"result\":\"ok\"}\n", $tcp->request(new Request('request', 3))->getData());
        $this->assertEquals("request\r\n", fread($peer, 1024));
    }

    protected function tearDown(): void
    {
        foreach ($this->pair as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->pair = [];
    }

    private function socketPair(): array
    {
        $this->pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        if (false === $this->pair) {
            $this->markTestSkipped('stream_socket_pair() is unavailable');
        }

        return $this->pair;
    }

    private function client($stream): Client
    {
        return new Client('socketpair', 0, new SocketPairStub($stream));
    }
}
