<?php

namespace Matasar\PhpTcp\Socket;

class StreamSocket extends AbstractSocket
{
    protected function open(string $host, string $port, ?float $timeout, &$errorCode, &$errorMessage)
    {
        return stream_socket_client(sprintf('tcp://%s:%d', $host, $port), $errorCode, $errorMessage, $timeout);
    }
}
