<?php

namespace Stub;

use RuntimeException;

class ServerStub
{
    /**
     * @var string
     */
    private static $request = '';

    /**
     * @var string
     */
    private static $response = '';

    /**
     * @var int
     */
    private static $readPointer = 0;

    /**
     * @var int
     */
    private static $timeout = 0;

    public static function start()
    {
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', self::class);
    }

    public static function stop()
    {
        stream_wrapper_restore('php');
    }

    public static function reset()
    {
        self::$timeout = 0;
        self::$response = '';
        self::$readPointer = 0;
    }

    /**
     * @param string $response
     * @param int $timeout
     */
    public static function setResponse(string $response, int $timeout = 0)
    {
        self::$response = $response;
        self::$timeout = $timeout;
    }

    /**
     * Removes sent request without finalizing line break
     *
     * @return string
     */
    public static function getRequest(): string
    {
        return rtrim(self::$request);
    }

    /**
     * @param int $count Always 8192 bytes
     *
     * @return string
     */
    public function stream_read(int $count)
    {
        if (self::$timeout > 0) {
            sleep(1);
            self::$timeout--;

            return '';
        }

        $data = trim(self::$response);

        if ('' === $data) {
            return false;
        }

        $length = strlen($data);
        $pointer = self::$readPointer;

        if ($pointer > $length) {
            self::$readPointer = 0;

            return '';
        }

        $chunkSize = $length < $count ? $length : $count;
        $chunk = substr($data, $pointer, $chunkSize);

        self::$readPointer += $chunkSize;

        return $chunk;
    }

    public function stream_write($data)
    {
        self::$request = $data;

        return mb_strlen(self::$request);
    }

    public function stream_seek()
    {
        throw new RuntimeException('Seems you forgot to stop the sub before asserting.');
    }

    public function stream_eof()
    {
        return false;
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return true;
    }

    public function stream_set_option(int $option , int $arg1)
    {
        return true;
    }
}
