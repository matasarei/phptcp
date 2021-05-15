<?php

namespace Matasar\PhpTcp\Socket;

use Matasar\PhpTcp\Exception\SocketException;

interface SocketInterface
{
    /**
     * @param string $host
     * @param string $port
     * @param float|null $timeout Connection timeout in seconds
     *
     * @return resource
     *
     * @throws SocketException
     */
    public function connect(string $host, string $port, ?float $timeout);
}
