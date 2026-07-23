<?php

namespace Stub;

use RuntimeException;

class ServerStub
{
    /**
     * @var resource|null Set by PHP's stream wrapper machinery
     */
    public $context;

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

    /**
     * @var bool
     */
    private static $replay = true;

    /**
     * @var bool
     */
    private static $closed = false;

    /**
     * @var int
     */
    private static $breakAfter = 0;

    /**
     * @var int
     */
    private static $writeLimit = 0;

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
        self::$request = '';
        self::$replay = true;
        self::$closed = false;
        self::$breakAfter = 0;
        self::$writeLimit = 0;
    }

    /**
     * @param string $response
     * @param int $timeout
     */
    public static function setResponse(string $response, int $timeout = 0)
    {
        self::$response = $response;
        self::$timeout = $timeout;
        self::$readPointer = 0;
    }

    /**
     * @param bool $replay Replay the response from the beginning once it has been fully read
     */
    public static function setReplay(bool $replay)
    {
        self::$replay = $replay;
    }

    /**
     * Simulates a connection closed by the peer: any remaining response data
     * is still readable, then the stream reports EOF
     */
    public static function close()
    {
        self::$closed = true;
    }

    /**
     * @param int $bytes Simulate a broken stream (read error) after this many bytes are read
     */
    public static function breakAfter(int $bytes)
    {
        self::$breakAfter = $bytes;
    }

    /**
     * @param int $limit Accept at most this many bytes per write to simulate partial writes
     */
    public static function setWriteLimit(int $limit)
    {
        self::$writeLimit = $limit;
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
     * @return string|false
     */
    public function stream_read(int $count)
    {
        if (self::$timeout > 0) {
            sleep(1);
            self::$timeout--;

            return '';
        }

        $data = self::$response;

        if ('' === $data) {
            return self::$closed ? '' : false;
        }

        if (self::$breakAfter > 0 && self::$readPointer >= self::$breakAfter) {
            return false;
        }

        $length = strlen($data);
        $pointer = self::$readPointer;

        if ($pointer > $length) {
            if (self::$replay && !self::$closed) {
                self::$readPointer = 0;
            }

            return '';
        }

        $chunkSize = $length < $count ? $length : $count;

        if (self::$breakAfter > 0) {
            $chunkSize = min($chunkSize, self::$breakAfter - $pointer);
        }

        $chunk = substr($data, $pointer, $chunkSize);

        self::$readPointer += $chunkSize;

        return $chunk;
    }

    public function stream_write($data)
    {
        if (self::$writeLimit > 0 && strlen($data) > self::$writeLimit) {
            $data = substr($data, 0, self::$writeLimit);
        }

        self::$request .= $data;

        return strlen($data);
    }

    public function stream_seek()
    {
        throw new RuntimeException('Seems you forgot to stop the sub before asserting.');
    }

    public function stream_eof()
    {
        return self::$closed && ('' === self::$response || self::$readPointer > strlen(self::$response));
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
