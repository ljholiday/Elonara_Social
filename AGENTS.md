# Repository Guidelines

## Project Structure & Module Organization
Elonara Social keeps application code under `src/`, grouped by domain (`App\Domain`), services (`App\Service*`), HTTP controllers (`App\Http`), and support modules (`Database`, `Security`, `Validation`). All classes autoload via PSR-4 from `composer.json`. Entry points live in `public/index.php` with `public/router.php` for the built-in server. Views are PHP templates in `templates/`, organized by feature and backed by `templates/base` layouts. Static assets sit in `assets/`; configuration lives in `config/` (copy `database.php.sample` to `database.php` before running). Database schema files are under `config/migrations` and `migrations/`, while developer tooling and seed scripts are in `dev/scripts/`. Legacy artifacts remain in `legacy/` and `pages/` for reference only. Executable smoke tests reside in `tests/`.

## Build, Test, and Development Commands
- `composer dump-autoload` refreshes the PSR-4 autoloader after refactoring `src/` classes.
- `php -S localhost:8000 public/router.php` starts a routing-aware PHP development server.
- `./install.sh` provisions the database using `config/schema.sql` and sanity checks connectivity.
- `./test.sh` executes every automated PHP test script in `tests/`.
- `php clear-cache.php` clears cached view/data artifacts after configuration or schema tweaks.
- `php dev/scripts/seed-modern-demo.php` seeds sample events and communities for the modern routes.

## External Documentation
- Most supporting docs now live in the sibling repository `../social_elonara-docs/`. Check there for refactor plans, style guides, and operational runbooks before starting new work.

## Coding Style & Naming Conventions
Follow PSR-12 with 4-space indentation, braces on new lines, and camelCase methods (reinforced in `dev/doctrine/php.xml`). Declare `<?php declare(strict_types=1); ?>` at the top of new files and use typed properties/arguments (PHP 8.1+). Mirror namespaces to directories (`App\Http\ControllerName`, `App\Service\...`). Templates should follow the `*-content.php` pattern; services end with `Service`; tests end in `*-test.php`.

## Testing Guidelines
Tests are standalone PHP scripts—name them `*-test.php` to stay compatible with `./test.sh`. Seed deterministic data with `dev/scripts/create-test-data.php` or `backfill-public-communities.php`. Point `config/database.php` to a disposable schema before running tests, and avoid side effects in assertions. Debug utilities belong in files prefixed `debug-`; the test harness skips them automatically.

## Commit & Pull Request Guidelines
Recent history favors short, sentence-style commit subjects (e.g., “Fix community invitation acceptance bugs”). Keep messages concise, cover the intent, and reference tickets when available. Every pull request should explain schema or configuration impacts, list new endpoints/templates, link related issues, and include screenshots or curl snippets for UI/API changes. Call out required follow-up scripts in `dev/scripts/` so reviewers can reproduce your setup.
