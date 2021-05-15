<?php

namespace Matasar\PhpTcp;

interface RequestInterface
{
    public function getBody(): string;

    /**
     * @return int Request timeout in seconds
     */
    public function getTimeout(): int;
}
