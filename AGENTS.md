# Repository Guidelines

## Project Structure & Module Organization
Source lives in `src/`, organized by domain namespaces (`App\Domain`, `App\Service*`, `App\Http`, and supporting modules such as `Database`, `Security`, `Validation`). Public entry points are `public/index.php` for production and `public/router.php` for the built-in server; templates reside in `templates/` with shared layouts under `templates/layouts/`. Static assets are in `public/assets/`, configuration belongs in `config/`, and migrations are split between `config/migrations/` and `migrations/`. Developer helpers and data scripts sit in `dev/scripts/`, and executable smoke tests are under `tests/`.

## Build, Test, and Development Commands
- `composer dump-autoload` refreshes PSR-4 mappings after renaming or moving classes in `src/`.
- `php -S localhost:8000 public/router.php` starts the lightweight router-aware dev server.
- `./install.sh` provisions the database using `config/schema.sql` and validates connectivity.
- `./test.sh` runs every `*-test.php` script in `tests/`; use before opening a pull request.
- `php clear-cache.php` clears cached views/data when configuration or schema changes.
- `php dev/scripts/seed-modern-demo.php` loads sample communities for UI smoke testing.

## Coding Style & Naming Conventions
Follow PSR-12: 4-space indentation, braces on new lines, camelCase methods. Begin new PHP files with `<?php declare(strict_types=1); ?>` and prefer typed properties/params (PHP 8.1+). Keep namespaces aligned with paths (e.g., `src/App/Service/FooService.php` → `App\Service\FooService`). Templates end with `*-content.php`, services with `Service`, tests with `*-test.php`, and front-end helpers use the `.app-` prefix inside `public/assets/css/app.css`.

## Testing Guidelines
Author deterministic PHP scripts named `*-test.php`; they are executed via `./test.sh` without additional tooling. Seed predictable fixtures through `dev/scripts/create-test-data.php` or `backfill-public-communities.php`, and target a disposable schema in `config/database.php` before running tests. Avoid side effects in assertions, and keep debugging aids in files prefixed `debug-` so the harness skips them.

## Commit & Pull Request Guidelines
Use short, sentence-style commit subjects (e.g., “Tighten invitation acceptance validation”). Commits should explain intent and reference tickets when possible. Pull requests must describe schema/config impacts, list new endpoints/templates, link related issues, and include screenshots or curl snippets for UI/API changes. If reviewers need seeders or maintenance scripts, call out the exact `dev/scripts/` entry point.

## Security & Configuration Tips
Copy `config/database.php.sample` to `config/database.php` and keep credentials out of version control. Re-run `./install.sh` plus `php clear-cache.php` after schema or config edits to keep local data coherent. Operational runbooks, deployment checklists, and refactor plans live in `../social_elonara-docs/`; review them before making architectural changes.
