<?php

namespace Matasar\PhpTcp\Socket;

class FSocket extends AbstractSocket
{
    protected function open(string $host, string $port, ?float $timeout, &$errorCode, &$errorMessage)
    {
        return fsockopen('tcp://' . $host, $port, $errorCode, $errorMessage, $timeout);
    }
}
