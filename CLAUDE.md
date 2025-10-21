# CLAUDE.md

Guidelines for Claude Code when touching this repository.

## Current Architecture Snapshot
- Full-modern PHP 8.1+ codebase under `src/` using PSR-4 autoloading (`App\…` namespaces).
- Entry point is `public/index.php`, which boots `src/bootstrap.php` and the lightweight DI container `AppContainer`.
- Controllers live in `src/Http/Controller/`, services in `src/Services/`, domain layers under `src/Domain/`, helpers under `src/Support/` & `includes/`.
- Views are PHP templates under `templates/`, backed by layouts in `templates/layouts/` and partials in `templates/partials/`.
- JavaScript sits in `public/assets/js/`, CSS in `public/assets/css/`, shared assets in `public/assets/`.
- Database schema: `config/schema.sql` (authoritative). Incremental SQL migrations reside in `config/migrations/`.
- Tests are executable PHP scripts in `tests/`. Run `./test.sh` to execute the full suite.

## Common Commands
```bash
./install.sh            # bootstrap database with schema.sql
./test.sh               # run all automated tests
php -S localhost:8000 public/router.php   # dev server with router awareness
php clear-cache.php     # drop cached view/data artifacts
php dev/scripts/seed-modern-demo.php  # seed sample data for demos
```

## Service Container Usage
- `app_container()` returns the singleton container instance.
- `app_service('service.id')` fetches registered services (e.g., `auth.service`, `mail.service`, `validation.validator`).
- Services are registered in `src/bootstrap.php`; keep registrations small, stateless, and typed.
- Templates and controllers should resolve dependencies via `app_service()` rather than instantiating classes directly.

## Request Flow (HTTP)
1. `public/index.php`
2. `src/bootstrap.php` (config + container wiring)
3. `src/Http/routes.php` registers routes on the `App\Http\Router` instance
4. Router dispatches to controller classes in `src/Http/Controller/`
5. Controllers orchestrate services and render templates via `app_render()`

## Coding Conventions
- PHP: PSR-12, strict types, camelCase methods, typed properties/params. New files start with `<?php declare(strict_types=1); ?>`.
- Templates: logic-light, use helpers from `templates/_helpers.php`. Avoid heavy logic in templates.
- JavaScript: no inline `<script>` blocks larger than a quick one-liner; real logic belongs in `public/assets/js/*.js`.
- CSS: all classes prefixed with `.app-`. Styles live in `public/assets/css/app.css`.
- Sanitization: boundary layers (controllers/templates) sanitize and validate with `validation.sanitizer` / `validation.validator` services before passing data downstream.
- Tests: create new scripts under `tests/` ending with `-test.php` so `./test.sh` picks them up.

## Safety + Tooling
- Never commit secrets; `config/config.php` is gitignored.
- Use `composer dump-autoload` after adding classes under `src/`.
- When touching database logic, confirm against `config/schema.sql` and update if schema changes.
- Keep JS event handlers off markup; prefer `data-*` hooks plus scripts in `public/assets/js/`.
- Reuse shared partials/components (e.g., `templates/partials/entity-card.php`) rather than duplicating markup.

## What *Not* To Do
- No references to the former WordPress/plugin stack remain; everything runs through the modern services registered in `src/bootstrap.php`.
- Do not mix presentation and logic layers (no SQL in templates, no HTML emitted from services).
- Avoid adding new global helper shims; extend services or add namespaced utilities instead.

## Quick Reference – Key Services
- `auth.service` → `App\Services\AuthService`
- `security.service` → `App\Services\SecurityService`
- `mail.service` → `App\Services\MailService`
- `conversation.service` → `App\Services\ConversationService`
- `community.service` → `App\Services\CommunityService`
- `event.service` → `App\Services\EventService`
- `sanitizer.service` / `validator.service` → validation pipeline

Keep changes consistent with the modern architecture and prefer extending the existing service/controller patterns over introducing new paradigms. When in doubt, check the matching item in `dev/doctrine/` for the domain-specific rules.
