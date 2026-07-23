<?php

use Matasar\PhpTcp\Client;
use Matasar\PhpTcp\Exception\ConnectionException;
use Matasar\PhpTcp\Exception\RequestException;
use Matasar\PhpTcp\Request;
use PHPUnit\Framework\TestCase;
use Stub\ServerStub;
use Stub\SocketStub;

class ClientTest extends TestCase
{
    public function testNoResponse()
    {
        ServerStub::setResponse('connected');

        $client = $this->createClient();
        $client->connect();

        ServerStub::setResponse('');

        $this->expectException(ConnectionException::class);

        $client->request(new Request('', 1));
    }

    public function dataTransferFixturesProvider(): array
    {
        return [
            'simple request' => [
                'simple request',
                'simple response'
            ],
            'more data' => [
                implode('', array_fill(0, 1024, 'a')),
                implode('', array_fill(0, 8192, 'b'))
            ]
        ];
    }

    /**
     * @dataProvider dataTransferFixturesProvider
     */
    public function testDataTransfer($requestData, $responseData)
    {
        ServerStub::setResponse($responseData);

        $client = $this->createClient();
        $client->connect();

        $response = $client->request(new Request($requestData, 1));

        $client->disconnect();

        $this->assertEquals($responseData, $response->getData());
        $this->assertEquals($requestData, ServerStub::getRequest());
    }

    public function testTimeout()
    {
        ServerStub::setResponse('connected');

        $client = $this->createClient();
        $client->connect();

        ServerStub::setResponse('response', 2);

        $this->expectException(RequestException::class);

        $client->request(new Request('', 1));
    }

    public function testBrokenStreamDuringRead()
    {
        ServerStub::setResponse('connected');

        $client = $this->createClient();
        $client->connect();

        ServerStub::setResponse('partial response');
        ServerStub::breakAfter(4);

        $this->expectException(ConnectionException::class);

        $client->request(new Request('', 1));
    }

    public function testConnectionClosedByPeer()
    {
        ServerStub::setResponse('connected');

        $client = $this->createClient();
        $client->connect();

        ServerStub::setResponse('');
        ServerStub::close();

        $this->expectException(ConnectionException::class);

        $client->request(new Request('', 10));
    }

    public function testDelimiterFraming()
    {
        ServerStub::setResponse("connected\n");

        $client = $this->createClient();
        $client->setDelimiter("\n");

        $this->assertEquals("connected\n", $client->connect()->getData());

        ServerStub::setResponse("{\"result\":\"ok\"}\n");

        $response = $client->request(new Request('request', 1));

        $this->assertEquals("{\"result\":\"ok\"}\n", $response->getData());
        $this->assertEquals('request', ServerStub::getRequest());
    }

    public function testDelimiterConnectionClosedBeforeCompleteResponse()
    {
        ServerStub::setResponse('connected');

        $client = $this->createClient();
        $client->connect();

        $client->setDelimiter("\n");
        ServerStub::setResponse('{"result":');
        ServerStub::close();

        $this->expectException(ConnectionException::class);

        $client->request(new Request('', 5));
    }

    public function testDelimiterTimeoutOnIncompleteResponse()
    {
        ServerStub::setResponse('connected');

        $client = $this->createClient();
        $client->connect();

        $client->setDelimiter("\n");
        ServerStub::setResponse('incomplete response');
        ServerStub::setReplay(false);

        $this->expectException(RequestException::class);

        $client->request(new Request('', 1));
    }

    public function testPartialWrites()
    {
        ServerStub::setResponse('connected');

        $client = $this->createClient();
        $client->connect();

        ServerStub::setWriteLimit(3);

        $client->request(new Request('chunked request body', 1));

        $this->assertEquals('chunked request body', ServerStub::getRequest());
    }

    public function testConnectionFailed()
    {
        $client = $this->createClient();

        $this->expectException(ConnectionException::class);

        $client->connect();
    }

    public function testConnectionResponse()
    {
        $client = $this->createClient();

        ServerStub::setResponse('connected');

        $this->assertEquals('connected', $client->connect()->getData());
    }

    protected function setUp(): void
    {
        ServerStub::start();
    }

    protected function tearDown(): void
    {
        ServerStub::reset();
        ServerStub::stop();
    }

    private function createClient(): Client
    {
        return new Client('localhost', 0, new SocketStub());
    }
}
