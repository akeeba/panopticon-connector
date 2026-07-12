# Tests

This repository has two independent PHPUnit test suites.

| Suite | Config | What it does | Needs |
|-------|--------|--------------|-------|
| **Unit** | `phpunit.xml` | Fast, pure-logic tests. No Joomla, no database, no network. | A PHPUnit 10/11 binary; `component/backend/vendor/` (run `composer install`). |
| **Integration** | `phpunit-integration.xml` | Drives the real REST API over HTTP against a freshly-installed Joomla on a Dockerised LAMP stack, for the latest Joomla 4, 5 and 6. | Docker, `phing` + the sibling `../buildfiles` repo, and host tools `curl`, `jq`, `gzip`, `unzip`. |

> **No PHPUnit in `composer.json` — by design.** This project redirects Composer's `vendor-dir` to the *shipped* `component/backend/vendor`, so a `require-dev` would leak PHPUnit into the release package. The unit suite uses whatever PHPUnit 10/11 you have on your `PATH` (or a downloaded `phpunit-11.phar`); the integration suite uses a PHPUnit phar baked into its Docker image.

## Unit suite

```bash
composer install                 # once, to populate component/backend/vendor/
phpunit -c phpunit.xml           # or: php phpunit-11.phar -c phpunit.xml
```

Covers pure logic only:
- `component/api/src/Library/ServerInfo.php` — the command-output parsers (`parseFreeOutput`, `parseMemoryPressure`, `parseLoad`, `parseProcStat`, `parseTopCpu`, `parseDiskFreeOutput`, `parseOsRelease`) plus `uptimeToMinutes()` / `getOSFamily()`.
- `component/api/src/Library/VersionStability.php` — `detectStability()` / `stabilityToString()` (semver classification).
- `component/api/src/Model/ElementToExtensionIdTrait.php` — `extensionNameToCriteria()`.
- `plugins/console/panopticon/src/Command/GetToken.php` — `computeApiToken()` (the Joomla API-token HMAC).
- `component/api/src/Library/JRegistryForAPIWorkaround.php`.

Some of these are *characterization* tests: they assert the code's **current** behaviour, including two known pre-existing bugs flagged in-line in `tests/unit/Library/ServerInfoTest.php` (`parseLoad` drops the 15-minute load average; `parseProcStat` always returns null due to a `str_starts_with` argument swap). If those bugs are fixed, update the corresponding assertions.

## Integration suite

Orchestrated by `tests/integration/run-tests.sh`, which:
1. Builds & starts the LAMP stack (`tests/integration/docker/`): Apache + PHP (`web`) and MariaDB (`db`).
2. For each requested Joomla major, resolves the **latest stable** version from the Panopticon checksums feed (`https://getpanopticon.com/checksums/sources.json.gz`), downloads it (cached in `tests/integration/inbox/`), extracts into the web root, and installs it via Joomla's CLI installer (`installation/joomla.php install`, adding `--public-folder` for Joomla ≥ 5).
3. Builds the real `pkg_panopticon` package with `phing git` and installs it via `php cli/joomla.php extension:install`.
4. Issues an API token with the shipped console command `php cli/joomla.php panopticon:token:get` (which also enables the `webservices/panopticon`, `api-authentication/token` and `user/token` plugins).
5. Runs the PHPUnit integration suite inside the `web` container against `http://web/api/index.php`.

```bash
tests/integration/run-tests.sh            # all available majors (4, 5, 6)
tests/integration/run-tests.sh 5          # a single major
tests/integration/run-tests.sh 4 5 --keep # keep the stack up afterwards
```

A major with no stable release yet (e.g. Joomla 6 before GA) is **skipped with a loud log line**, not silently dropped.

### Configuration

`tests/integration/docker/env.dist` is the template; `run-tests.sh` copies it to `.env` (git-ignored) on first run. Override `PHP_VERSION`, `DB_IMAGE`, `WEB_PORT`, DB credentials there. The tests read two environment variables, injected automatically by the orchestrator:

- `PANOPTICON_BASE_URL` — the API root (default `http://web/api/index.php`).
- `PANOPTICON_API_TOKEN` — the Bearer token for the test Super User.

### Layout

```
tests/
├── unit/                 # pure-logic tests + bootstrap
└── integration/
    ├── run-tests.sh      # orchestrator
    ├── lib.sh            # Joomla resolve/download/install shell helpers
    ├── bootstrap.php
    ├── docker/           # docker-compose.yml, Dockerfile.web, php.ini, env.dist, www/ (web root)
    ├── inbox/            # cached Joomla packages (git-ignored)
    └── src/
        ├── AbstractApiTestCase.php
        └── Tests/        # SmokeTest, AuthorizationTest, ExtensionsTest, UpdatesTest, BackupTest
```
