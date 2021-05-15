<?php

namespace Matasar\PhpTcp;

class Request implements RequestInterface
{
    const TIMEOUT_DEFAULT = 30;

    /**
     * @var string
     */
    private $body;

    /**
     * @var int
     */
    private $timeout;

    public function __construct(string $body, int $timeout = self::TIMEOUT_DEFAULT)
    {
        $this->body = $body;
        $this->timeout = $timeout;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
