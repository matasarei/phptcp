<?php

namespace Stub;

use Matasar\PhpTcp\Socket\SocketInterface;

class SocketStub implements SocketInterface
{
    public function connect(string $host, string $port, ?float $timeout = null)
    {
        return fopen('php://' . $host, 'w+');
    }
}
