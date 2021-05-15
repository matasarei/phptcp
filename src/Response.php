<?php

namespace Matasar\PhpTcp;

class Response
{
    /**
     * @var string
     */
    private $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->data);
    }
}
