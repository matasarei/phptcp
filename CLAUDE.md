# php-tcp — notes for Claude

A dependency-free TCP client library (`matasarei/php-tcp`, namespace `Matasar\PhpTcp`). It wraps
PHP's native stream functions; the only runtime dependency is the PSR-3 logger *interface*.
There is nothing to run — the test suite is the entire runtime surface, and the public API is a
published contract, so a signature change is a breaking change for consumers.

**PHP 7.4 is the supported floor.** The host has no PHP, and the container ships 8.5, so nothing
locally will catch 7.4-incompatible syntax — no constructor promotion, union types, `mixed`,
`match`, enums, `readonly`, nullsafe `?->` or first-class callables. Only CI proves it.

## Commands

The host has neither `php` nor `composer`; everything runs in `composer:2` — a floating tag,
currently Composer 2.10.x on PHP 8.5. Not `composer:lts`, which is Composer 2.2 and has no
`composer audit`.

| What | Command |
|---|---|
| Install | `docker run --rm -v "${PWD}":/app -w /app composer:2 composer install --no-interaction` |
| Test | `docker run --rm -v "${PWD}":/app -w /app composer:2 vendor/bin/phpunit` |
| One test | `… composer:2 vendor/bin/phpunit --filter <name>` |
| Lint | `docker run --rm -v "${PWD}":/app -w /app composer:2 php -l <file>` |
| Audit | `docker run --rm -v "${PWD}":/app -w /app composer:2 composer audit` |
| Build | none — nothing is compiled |
| Run | none — a library; drive it through the suite |

A green local run only proves PHP 8.5. CI (`.github/workflows/tests.yml`) runs the suite on
7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5 — that matrix is the real gate.

`timeout`/`gtimeout` are both absent here, so a hung command cannot be bounded automatically —
say so rather than pretending a check completed. `phpunit.xml` sets `stopOnFailure="true"`: the
first failure ends the run, so a "1 failure" report may be hiding others.

<!-- toolkit:begin family-rules -->

## PHP conventions

### Style

- **4 spaces**, Unix line endings, no trailing whitespace, no closing `?>`.
- **Strict comparison** — `===` and `!==`. `==` hides type juggling bugs.
- **Type hints and return types** on everything new, including `void`. Nullable types are
  explicit (`?string`), never implied by a `null` default. Keep them inside the 7.4 subset.
- **`DateTimeImmutable`**, not `DateTime` — a mutable date passed into a method and modified
  there is a bug that reproduces only under specific ordering.
- Short array syntax `[]`. Trailing comma on multi-line arrays and argument lists.
- **Names are words, not abbreviations** — `$entityManager`, not `$em`. Interfaces end in
  `Interface`.
- **Return early.** No `else` after a `return`; no assignments inside conditions.
- Comments explain **why**, never what. A comment restating the code is noise that goes stale.
- Prefer composition over inheritance.

### Design priorities

Prefer the design that is easier to read, then easier to change, then easier to extend, then
cheaper in memory and time — in that order. Efficiency is last, not absent: weigh it at the scale
the data actually has, and give an optimisation that costs readability a measured reason.

### Code provenance

New code is written here, installed as a dependency through the package manager, or taken from
code under this project's own licence with its header kept. Code from a codebase under a
different licence — closed, or open source, MIT included — needs the developer's approval
first and its source and licence named in the commit; renaming a pasted block does not make it
original, and a library the project could depend on is not vendored piecemeal.

### Security

Treat these as blockers, not preferences.

- **Never** `eval()`, `extract()` on caller data, `unserialize()` on anything read off a socket,
  or a shell call built from it. Everything this library returns is remote input — a response is
  attacker-controlled by definition, and the caller decides what it means.
- **Bound what you read.** An unbounded read from a peer is a memory exhaustion bug; a missing
  timeout is a hang. Both are the library's responsibility, not the caller's.
- **Errors**: log the detail, throw a message that names no credential and no internal path.
  Never log the payload wholesale — it may carry a password or a token.
- **Secrets** never appear in code, tests or fixtures. Hosts, ports and credentials belong to
  the consumer's configuration.

### Dependencies

- The manifest is committed; `composer.lock` is deliberately **not** — a library resolves
  against its consumers' constraints, and CI runs `composer update` for that reason.
- Adding a runtime dependency to a zero-dependency library is an API decision, not an
  implementation detail. Raising the PHP floor is a **major** version.

<!-- toolkit:end family-rules -->

## This project specifically

- **Public API is the contract.** `Client`, `Request`, `Response`, `SocketInterface` and the
  three exceptions are what consumers bind to. `SocketInterface` has one method precisely so
  third parties can implement it — adding to it breaks every external implementation.
- **Deprecations are kept, not removed** — see `Client::DEFAULT_CONNECTION_LAG`. Follow that
  pattern rather than deleting a constant or method.
- `vendor/`, `composer.lock`, `.phpunit.result.cache` and `.claude/` are generated or local;
  none are committed.
- **Releasing** = tag + push. Packagist reads `composer.json` per tag, so the name in a tag's
  `composer.json` must match the registered package (`matasarei/php-tcp`) or that version is
  silently skipped. Update `CHANGELOG.md` in the same change.
- No database, no configuration file, no credentials anywhere in the repository.

### To confirm

- Existing files do **not** declare `strict_types`. Adding it to new files only is safe (the
  directive is per-file) but leaves the codebase mixed — decide one way and record it here.
