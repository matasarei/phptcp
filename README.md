# PhpTcp

[![Tests](https://github.com/matasarei/phptcp/actions/workflows/tests.yml/badge.svg)](https://github.com/matasarei/phptcp/actions/workflows/tests.yml)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/matasarei/phptcp/php.svg)](composer.json)
[![License](https://img.shields.io/packagist/l/matasarei/phptcp.svg)](LICENSE)

A lightweight TCP client for PHP with no runtime dependencies beyond a PSR-3 logger interface.
It wraps PHP's native stream functions in a small, testable API: pluggable socket transports,
configurable timeouts, optional delimiter-based response framing and PSR-3 logging.

Well suited for simple text-based request/response protocols — line-delimited JSON,
JSON-RPC over TCP, custom device or legacy service protocols, and similar.

## Features

- Simple connect / request / disconnect API over plain TCP
- Two built-in transports — `StreamSocket` (`stream_socket_client`) and `FSocket` (`fsockopen`) —
  plus a one-method `SocketInterface` for custom transports
- Optional end-of-response delimiter for line-based protocols (see [Response framing](#response-framing))
- Detects broken streams and connections closed by the peer; completes partial writes
- Configurable connection, request and read timeouts
- PSR-3 (`psr/log` v1, v2 or v3) logger support for debugging

## Requirements

- PHP 7.4 or newer

## Installation

```bash
composer require matasarei/phptcp
```

## Quick start

```php
use Matasar\PhpTcp\Client;
use Matasar\PhpTcp\Request;
use Matasar\PhpTcp\Socket\StreamSocket;

$client = new Client('ip_or_hostname', 8888, new StreamSocket());
$client->connect();

$response = $client->request(new Request('request data'));
$client->disconnect();

var_dump($response->getData());
```

`Request` accepts an optional per-request timeout in seconds (default 30):

```php
$request = new Request('request data', 5);
```

## Socket transports

The library includes two transports: `StreamSocket` and `FSocket`.
The difference is `stream_socket_client()` vs `fsockopen()` under the hood;
pick whichever you prefer, or implement `Socket\SocketInterface` to supply your own
(e.g. for TLS wrappers or unit-test stubs).

Both transports accept a blocking timeout (seconds, default 1) that controls how long
a single read waits for data before reporting "no data yet":

```php
use Matasar\PhpTcp\Socket\FSocket;

new FSocket(2); // wait up to 2 seconds per read cycle
new FSocket(0); // non-blocking mode
```

## Client settings

```php
use Matasar\PhpTcp\Client;
use Matasar\PhpTcp\Socket\FSocket;

$client = new Client('hostname', 1234, new FSocket());

$client->setChunkSize(16384);        // read data by 16 KB per cycle (default 8 KB).
$client->setPollInterval(5000);      // wait 5 ms between data availability checks (default 1 ms).
$client->setDelimiter("\n");         // treat "\n" as the end of a response (see below).
$client->setLogger(new PsrLogger()); // any PSR-3 logger, for debugging.

$client->connect(5); // connection timeout in seconds (default 2).
```

## Response framing

By default, the client considers a response complete when the server stops sending data
for a moment (a silent interval on the stream). This works without any protocol knowledge,
but it has two downsides: every read costs an extra blocking-timeout interval, and a server
that stalls mid-response can have its reply cut short.

If your protocol marks the end of a message — like line-based protocols such as JSON-RPC
over TCP — set a delimiter instead:

```php
$client->setDelimiter("\n");
```

With a delimiter set, the client returns as soon as the response ends with the delimiter
(the delimiter is kept in the response data). It throws a `RequestException` if a complete
response does not arrive within the request timeout, and a `ConnectionException` if the
connection is closed before the response is completed.

## Error handling

All exceptions live under `Matasar\PhpTcp\Exception`:

| Exception             | Thrown when                                                                    |
|-----------------------|--------------------------------------------------------------------------------|
| `ConnectionException` | Connection could not be established, is already open, was closed by the peer, or the stream broke mid-transfer. |
| `RequestException`    | No (or no complete) response arrived within the request timeout.               |
| `SocketException`     | Low-level transport failure; wrapped into `ConnectionException` by `connect()`. |

```php
use Matasar\PhpTcp\Exception\ConnectionException;
use Matasar\PhpTcp\Exception\RequestException;

try {
    $client->connect();
    $response = $client->request($request);
} catch (ConnectionException $exception) {
    // failed to connect / connection lost
} catch (RequestException $exception) {
    // the server did not respond in time
}
```

## Testing

The test suite runs against PHP 7.4–8.5 in CI.

Locally, the easiest way is Docker — no PHP or Composer installation required:

```bash
docker run --rm -v $(pwd):/app -w /app composer:lts composer install
docker run --rm -v $(pwd):/app -w /app composer:lts vendor/bin/phpunit
```

Or with a local PHP setup:

```bash
composer install
vendor/bin/phpunit
```

## License

Released under the [MIT license](LICENSE).
