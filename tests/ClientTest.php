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

    protected function setUp()
    {
        ServerStub::start();
    }

    protected function tearDown()
    {
        ServerStub::reset();
        ServerStub::stop();
    }

    private function createClient(): Client
    {
        return new Client('localhost', 0, new SocketStub());
    }
}
