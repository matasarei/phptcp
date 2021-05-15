# PHP TCP
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
```
