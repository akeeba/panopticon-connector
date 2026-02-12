# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Akeeba Panopticon Connector is a **Joomla 4/5 component** (PHP) that exposes REST API endpoints for [Akeeba Panopticon](https://github.com/akeeba/panopticon), a self-hosted site monitoring tool. It allows Panopticon to remotely monitor updates, install extensions, verify core file checksums, and interact with Akeeba Backup and Admin Tools on connected Joomla sites.

**License:** AGPL-3.0+

## Build Commands

The project uses **Apache Phing** with shared build infrastructure from a sibling `buildfiles` directory (`../buildfiles/phing/common.xml`).

```bash
composer install                    # Install PHP dependencies (vendored into component/backend/vendor/)
phing git                           # Build packages (no documentation)
phing all                           # Build everything including update XML
phing component-packages            # Build just the component packages
phing new-release                   # Clean release/ dir and prepare for packaging
```

There is no test suite, linter, or CI pipeline configured.

## Architecture

### Joomla Component (MVC)

The core component lives in `component/` following Joomla's standard structure:

- **`component/api/src/`** — REST API layer (the main purpose of this project)
  - `Controller/` — API controllers: `CoreController`, `ExtensionsController`, `UpdatesController`, `UpdatesitesController`, `AdmintoolsController`, `BackupController`, `TemplatechangedController`
  - `Model/` — Business logic models corresponding to each controller
  - `View/` — JSON:API views (using Tobscure's JsonApi serializer)
  - `Library/` — Helper classes (e.g., `ServerInfo` for system metrics)
  - `Mixin/` — Shared traits (`J6FixBrokenModelStateTrait`, `ElementToExtensionIdTrait`)
- **`component/backend/`** — Joomla admin panel (minimal — just options/configuration)
  - `services/provider.php` — Service provider (DI registration)
  - `version.php` — Version constants (`AKEEBA_PANOPTICON_VERSION`, `AKEEBA_PANOPTICON_DATE`, `AKEEBA_PANOPTICON_API`)
  - `vendor/` — Composer dependencies (vendored here via `composer.json` config)

### Plugins

Three companion plugins in `plugins/`:

- **`plugins/webservices/panopticon/`** — Registers all API routes under `/v1/panopticon/`. This is the route definition file — all API endpoints are declared in `src/Extension/Panopticon.php`.
- **`plugins/system/panopticon/`** — Error handling for API requests.
- **`plugins/console/panopticon/`** — CLI command for generating API tokens.

### API Route Pattern

All routes are prefixed with `v1/panopticon/` and map to controller actions. The route → controller mapping is entirely defined in the WebServices plugin. Controllers handle authorization via Super User checks and return JSON:API formatted responses.

### Key Integration Points

The connector optionally integrates with:
- **Akeeba Backup Professional** — backup status and version info
- **Admin Tools Professional** — IP unblocking, .htaccess management, file change scanning, temporary super users

These integrations are detected at runtime; the connector works without them.

## Coding Conventions

- **Namespaces:** `Akeeba\Component\Panopticon\Api\*`, `Akeeba\Component\Panopticon\Administrator\*`, `Akeeba\Plugin\{Group}\Panopticon\*`
- **File header:** Every PHP file starts with the standard `@package panopticon` / copyright / license block, followed by `defined('_JEXEC') || die;`
- **Indentation:** Tabs, not spaces
- **Controllers** extend Joomla's `ApiController`; **Models** extend `ListModel` or `BaseModel`; **Views** extend `JsonapiView`
- **Authorization:** Controllers check for Super User access; unauthorized requests throw `NotAllowed` exceptions
- **Version tokens:** Build templates use `##VERSION##` and `##DATE##` placeholders replaced by Phing

## Versioning

- Version, date, and API level are maintained in `component/backend/version.php`
- The API level (`AKEEBA_PANOPTICON_API`) is an integer that tracks breaking API changes
- Package manifests (`pkg_panopticon.xml`, `component/panopticon.xml`) also contain version strings updated at build time
