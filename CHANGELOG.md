# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `Client::setMaxResponseSize()` and `Client::DEFAULT_MAX_RESPONSE_SIZE` — a response is now
  capped at 8 MiB by default and throws a `RequestException` beyond it. Pass `null` for the
  previous unlimited behaviour.
- `StreamSocket` and `FSocket` take a second constructor argument, `$blocking`. The first
  argument now only sets the read timeout; previously it decided both, so a positive timeout
  forced a blocking stream. Defaults keep the old behaviour.

### Fixed
- **Security:** neither transport pinned a scheme, so a host such as `udp://127.0.0.1` opened a
  UDP socket. `StreamSocket` and `FSocket` now build a `tcp://` address and reject a host
  containing `://` with a `SocketException`.
- **Security:** the read timeout was only checked when a read came back empty, so a peer that
  kept sending was never timed out and the response grew until the process ran out of memory.
  The elapsed time is now checked on every iteration.
- The debug log carried the whole `Request` object, putting the request body — credentials
  included — into the consumer's log. It now carries the body length.
- `Client::connect()` accepts a `float` timeout. It had no parameter type and passed the value
  to an `int` parameter, so `connect(0.5)` became `0` and raised an implicit-conversion
  deprecation on PHP 8.1+.
- A read is bounded by one timeout rather than two: the clock started after the initial wait had
  already returned, so a request could take twice the timeout the caller asked for.
- `phpunit.xml` no longer sets `stopOnFailure`, so a failing run reports every failure.

### Changed
- The no-response exception message is now `Request timeout, no response.` — it previously
  carried a stray backslash. Code matching on that string needs updating.
- `StreamSocket` and `FSocket` type their first constructor argument as `int`; a value that is
  not an integer now raises a `TypeError` instead of being coerced. Same for
  `Client::connect()`, which is now `float` and no longer accepts `null`.

## [1.2.2] — 2026-09-07

### Changed
- Package renamed from `matasarei/phptcp` to `matasarei/php-tcp`, and the repository
  renamed to match — install with `composer require matasarei/php-tcp`. The PHP
  namespace `Matasar\PhpTcp` is unchanged, so no code changes are required.
  Versions 1.0–1.2.0 were published under the old package name and remain available
  only there; `matasarei/php-tcp` starts at 1.2.2.

## [1.2.0] — 2026-07-23

### Added
- `Client::setDelimiter()` — optional end-of-response marker (e.g. `"\n"` for
  line-based protocols such as JSON-RPC over TCP). With a delimiter set, a response
  is complete as soon as it ends with the delimiter instead of after a silent
  interval on the stream: reads return sooner and truncated responses are detected
  (`RequestException` on timeout, `ConnectionException` when the connection closes
  before the response is completed) rather than silently accepted.
- `Client::setPollInterval()` and `Client::DEFAULT_POLL_INTERVAL` — renamed from
  "connection lag": the value is the pause between data availability checks.
- CI: GitHub Actions matrix testing PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5.

### Fixed
- Infinite loop on a broken stream: `read()` looped on `fread() !== ''`, but
  `fread()` returns `false` on error, so a broken stream spun forever. It now
  throws a `ConnectionException`.
- A connection closed by the peer looked like a timeout: neither read loop checked
  `feof()`, so a closed connection kept polling for the full request timeout
  (30 s by default) and then reported a timeout. It now fails fast with
  `ConnectionException`.
- Unchecked and partial writes: `request()` ignored the `fwrite()` result, so large
  request bodies could be partially written with no error surfaced. Writes now
  continue until the whole body is sent; a zero-byte write (full send buffer in
  non-blocking mode) is retried until the request timeout.

### Changed
- Default chunk size raised from 1024 to 8192 bytes to match the size of PHP's
  internal stream read buffer — smaller reads only added loop iterations.
- `psr/log` v1, v2 and v3 are now accepted (`Client::setLogger()` returns `void`
  per the v3 interface).
- Minimum PHP version raised from 7.1 to 7.4.
- Test suite migrated from PHPUnit 7 to PHPUnit 9.6 (PHPUnit 7 is refused by
  current Composer due to a security advisory).
- README rewritten: project overview, badges, installation, transports, client
  settings, response framing and error-handling reference.

### Deprecated
- `Client::setConnectionLag()` — use `setPollInterval()` instead.
- `Client::DEFAULT_CONNECTION_LAG` — use `DEFAULT_POLL_INTERVAL` instead.

### Removed
- Unused `ext-json` requirement from `composer.json`.

### Backward-compatibility notes
- Wire behaviour without a delimiter is unchanged.
- A peer-closed connection now throws `ConnectionException` promptly instead of
  `RequestException` after the full timeout — code that caught only
  `RequestException` for that case should also catch `ConnectionException`.

## [1.1] — 2023-12-02

### Fixed
- Disconnect on connection / request failure so a failed client is not left in a
  half-open state.

## [1.0] — 2021-09-01

Initial release: a minimal TCP client (`Client`, `Request`, `Response`) with
pluggable socket transports (`StreamSocket`, `FSocket`), configurable timeouts,
chunked reads and PSR-3 logging.

[Unreleased]: https://github.com/matasarei/php-tcp/compare/1.2.2...HEAD
[1.2.2]: https://github.com/matasarei/php-tcp/compare/1.2.0...1.2.2
[1.2.0]: https://github.com/matasarei/php-tcp/compare/1.1...1.2.0
[1.1]: https://github.com/matasarei/php-tcp/compare/1.0...1.1
[1.0]: https://github.com/matasarei/php-tcp/releases/tag/1.0
