# PHP TCP
![example workflow](https://github.com/matasarei/phptcp/actions/workflows/tests.yml/badge.svg)

A TCP client for PHP. This is a demo library: it is provided as is, without any guarantees.

## Basic usage
```php
use Matasar\PhpTcp\Client;
use Matasar\PhpTcp\Request;
use Matasar\PhpTcp\Socket\StreamSocket;

$client = new Client('ip_or_hostname', 8888, new StreamSocket());
$client->connect();

$request = new Request('request');
$response = $client->request($request);
$client->disconnect();

var_dump($response->getData());
```

## Socket interface
The lib includes two socket interfaces to use: `StreamSocket` and `FSocket`. 
The difference is between using `stream_socket_client` and `fsockopen`. 
Choose one you need or like more or implement your own class.

Also, there is the blocking setting you can change:
```php
use Matasar\PhpTcp\Socket\FSocket;

new FSocket(0); // disable blocking.
```

## Client settings
```php
use Matasar\PhpTcp\Client;
use Matasar\PhpTcp\Socket\FSocket;

$client = new Client('hostname', 1234, new FSocket());

$client->setChunkSize(8192); // read data by 8 Kb per cycle.
$client->setConnectionLag(5000); // 5 ms pause per cycle.
$client->setLogger(new SomePsrLogger()); // connect a logger for debugging.
$client->setDelimiter("\n"); // treat "\n" as the end of a response (see below).
```

## Response framing
By default, the client considers a response complete when the server stops sending data for a moment
(a silent interval on the stream). This works for simple cases but has two downsides: every read costs
an extra blocking-timeout interval, and a slow server can be cut off in the middle of a response.

If your protocol marks the end of a message (e.g. line-based protocols such as JSON-RPC over TCP),
set a delimiter instead:

```php
$client->setDelimiter("\n");
```

With a delimiter set, the client returns as soon as the response ends with the delimiter
(the delimiter is kept in the response data), and throws a `RequestException` if a complete
response does not arrive within the request timeout.
