# Contributing to Elonara Social

Thanks for helping build Elonara Social — a modern PHP 8.1+ application that powers communities, events, and Bluesky-driven invitations. This guide sets expectations for development workflow, architecture, and quality so every change keeps the platform stable and secure.

---

## 1. Quick Reference

| Category                | Expectation                                                                 |
|-------------------------|-----------------------------------------------------------------------------|
| PHP version             | 8.1+ with `declare(strict_types=1);`                                        |
| Coding standard         | PSR-12 (4 spaces, braces on new lines, camelCase methods)                   |
| Dependency management   | Composer (autoloading via PSR-4 in `composer.json`)                         |
| Tests                   | Stand-alone PHP scripts in `tests/`, run with `php tests/foo-test.php`      |
| Frontend assets         | Static templates in `templates/`, vanilla JS in `public/assets/js/`         |
| Bluesky integration     | Services in `src/Services/BlueskyService.php`, invitations via `/rsvp/{token}` |
| Commit message style    | Concise imperative sentence (e.g., “Add Bluesky RSVP acceptance flow”)      |

---

## 2. Repository Layout

```
src/
  Domain/              Domain models and shared DTOs
  Http/                Router, controllers, request abstraction
  Services/            Application services (Auth, Invitation, Bluesky, etc.)
  Database/            Lightweight PDO wrapper and migrations helpers
templates/             PHP view templates (page-content.php convention)
public/                Public entry point, assets, router for PHP built-in server
tests/                 Executable PHP scripts (no framework)
config/                Environment configuration (copy `.sample` files locally)
dev/                   Tooling, scripts, and seeding utilities
docs/                  Project documentation (this file lives here)
```

Key entry points:

- `public/index.php` boots the container and dispatches via `src/Http/routes.php`.
- `src/bootstrap.php` registers services with the minimal `VTContainer`.
- `templates/layouts/*.php` wrap template content (two-column, form, page, etc.).

---

## 3. Development Workflow

1. **Sync main & create a branch**
   ```bash
   git checkout main && git pull
   git checkout -b feature/my-change
   ```
2. **Install dependencies**
   ```bash
   composer install
   ```
3. **Create `config/database.php`** from the provided sample and point it to a disposable schema.
4. **Run topical tests** that match your changes (see Section 7).
5. **Commit** with a concise subject and push for review.

Use short-lived branches and keep PRs focused — the repo favors reviewable, incremental improvements.

---

## 4. Coding Guidelines

### PHP

- Every file starts with `<?php declare(strict_types=1); ?>`.
- Method names use `camelCase`; class names use `StudlyCase`.
- Prefer dependency injection via the service container. Do not instantiate services directly inside other services — resolve them in `src/bootstrap.php`.
- Return typed arrays (`array{key:type,...}`) where practical for structured responses.
- Database access lives in service classes; use prepared statements with bound parameters.

### Templates (`templates/*.php`)

- Keep logic minimal—prepare data in controllers/services, render in templates.
- Escape output with `htmlspecialchars()` or helper functions.
- No inline scripts longer than three lines; place behavior in `public/assets/js`.
- Name templates using the `*-content.php` pattern and include them with `vt_render`.

### JavaScript (`public/assets/js`)

- Vanilla JS only; no bundlers.
- Namespace custom data attributes (`data-app-*`) when adding hooks.
- Keep fetch requests centralized where possible (e.g., invitation flows in `communities.js`).

### CSS

- Use existing `app-` prefixed utility classes where possible. New styling belongs in `public/assets/css/app.css` with the same prefix.

---

## 5. Architecture & Patterns

### Service Container

`VTContainer` provides a simple dependency injection layer. Register new services in `src/bootstrap.php`:

```php
$container->register('my.service', static fn (VTContainer $c): MyService => new MyService(
    $c->get('database.connection'),
));
```

Controllers and routes resolve services with `app_service('my.service')`.

### Boundary Validation

Follow the boundary pattern:

1. **Boundary layer** (templates, controllers): validate and sanitize input using `SanitizerService` or `ValidatorService`.
2. **Services**: assume data is already clean; enforce business rules and interact with the database.
3. **Database**: only receives trusted, typed data.

Never call validators inside services — it leads to inconsistent error handling and breaks automated tests.

### Routing

- Define HTTP routes in `src/Http/routes.php`.
- Use closures that resolve controllers/services and return rendered templates or JSON. Always set appropriate headers for JSON responses.

### Bluesky Integration

- Credential handling lives in `src/Services/BlueskyService.php`. It stores DID/handle pairs in `vt_member_identities`.
- Invitation flows are coordinated by `src/Services/InvitationService.php`:
  - Community invites accept via `/invitation/accept?token=...`.
  - Event invites accept via `/rsvp/{token}` (see `templates/guest-rsvp.php`).
- Bluesky-only invitations tag guests with `invitation_source = 'bluesky'` and prefix email fields with `bsky:` followed by the DID.

---

## 6. Database & Migrations

- Schema lives in `config/schema.sql`. Use `/config/migrations` for incremental changes.
- Keep migrations idempotent. When adding tables/columns, document them in commit messages and PR descriptions.
- Seed data with scripts in `dev/scripts/` (e.g., `seed-modern-demo.php`, `create-test-data.php`).

---

## 7. Testing

Tests are plain PHP scripts intentionally free from frameworks so they can run in constrained environments.

- **Run all tests**:
  ```bash
  ./test.sh
  ```
- **Run individual tests**:
  ```bash
  php tests/invitation-bluesky-test.php
  php tests/invitation-event-bluesky-test.php
  ```

Add new tests for non-trivial changes. Place them in `tests/` with a `*-test.php` suffix and ensure they clean up after themselves (delete inserted rows, etc.).

---

## 8. Git & Review Checklist

Before submitting a PR:

- [ ] Feature or fix aligns with project values (privacy, clarity, maintainability).
- [ ] New services registered in `src/bootstrap.php`.
- [ ] Input sanitized/validated at the boundary; services trust their parameters.
- [ ] Database modifications use prepared statements.
- [ ] User-facing copy is neutral and free of emojis.
- [ ] Relevant tests updated/added and run locally.
- [ ] Commit message succinctly summarises the change.
- [ ] For Bluesky flows, verify:
  - [ ] DID matching during acceptance.
  - [ ] Bluesky badges render for `bsky:` invitations.
  - [ ] `/rsvp/{token}` works for both Bluesky and email guests.

During review:

- Prioritize security (nonces, authorization, data exposure).
- Confirm templates escape output and avoid inline JS/SQL.
- Watch for duplicated logic—prefer using existing services/helpers.

---

## 9. Support & Questions

- **Environment issues**: check the sibling `../vivalatable-docs/` repository for historical context and runbooks.
- **New contributors**: start with small fixes in `templates/` or `tests/` to get familiar with the framework.
- **Contact**: open a draft PR or discussion thread summarizing the challenge and proposed approach.

Thanks again for contributing — your work helps keep Elonara Social safe, privacy-first, and Bluesky-friendly. Happy building!
