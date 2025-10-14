# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Elonara Social is a PHP-based community and event management platform using a custom MVC framework. The application is in active migration from legacy static classes to modern dependency injection architecture.

## Common Development Commands

### Development Server
```bash
# Start local PHP development server
php -S localhost:8000 -t .
```

### Database Operations
```bash
# Fresh installation (imports schema.sql)
./install.sh

# Test database connection and verify tables
php -r "require_once 'includes/bootstrap.php'; \$db = VT_Database::getInstance(); var_dump(\$db);"
```

### Testing
```bash
# Run all tests
./test.sh

# Syntax check single file
php -l path/to/file.php
```

### Cache Management
```bash
# Clear application cache
php clear-cache.php
```

## Architecture

### Hybrid Architecture Pattern
The codebase is undergoing a **gradual migration** from legacy static classes to modern dependency injection:

- **Legacy Layer**: Static `VT_*` classes (e.g., `VT_Database`, `VT_Auth`, `VT_Security`)
- **Modern Layer**: Namespaced services with dependency injection (e.g., `VT_Database_Connection`, `VT_Http_Request`)
- **Compatibility Layer**: `Container::createCompatibilityLayer()` bridges both systems
- **Service Access**: Use `vt_service('service.name')` to get modern services from anywhere

**CRITICAL**: Never mix architectural patterns within a single file. When modifying code:
- If file uses static classes, continue with static classes
- If file uses dependency injection, continue with dependency injection
- Don't convert files between patterns unless explicitly requested

### Request Flow
1. `index.php` → loads `includes/bootstrap.php`
2. `bootstrap.php` → initializes Container, loads all classes, starts session
3. `VT_Router::init()` → registers routes and dispatches request
4. Routes map to `VT_Pages::method()` handlers
5. Pages render templates from `templates/` directory

### Router System
Custom PHP router in `includes/class-router.php` handles all routing (no Apache rewrites for routes):

- **Pattern**: `VT_Router::addRoute($method, $path, [$class, $method])`
- **URL Parameters**: Use `{param}` syntax (e.g., `/events/{slug}`)
- **Route Order**: Specific routes must come before general routes
- **Access Params**: `VT_Router::getParam('slug')` or `VT_Router::getCurrentParams()`

### Database Schema
**Source of Truth**: `config/schema.sql` is the authoritative database structure.

- New installations: `install.sh` imports `schema.sql` directly
- Schema changes: Export development database to `schema.sql` before committing
- Migrations in `config/migrations/` are numbered SQL files for incremental changes
- Never rely on migration system for fresh installs

### Template System
Three-layer template architecture:

1. **Base Templates** (`templates/base/`): `template-page.php`, `template-form.php`, `template-two-column.php`
2. **Content Templates** (`templates/*-content.php`): Specific page content
3. **Partials** (`templates/partials/`): Reusable components

Rendering: `VT_Pages::renderPage($content_template, $title, $description, $base_template, $data)`

### Service Container
Modern services registered in `includes/Container.php`:

- `database.connection` - PDO database connection
- `database.query` - Query builder
- `http.request` - HTTP request handler
- `security.service` - Security/CSRF
- `auth.service` - Authentication
- `validation.sanitizer` - Input sanitization
- `validation.validator` - Input validation
- `embed.service` - URL embed handling
- `image.service` - Image processing

Access via: `vt_service('service.name')`

## Code Standards

### Critical Rules from dev/doctrine/

**Language Separation**:
- PHP = logic only (in `includes/`)
- HTML = structure only (in `templates/`)
- CSS = presentation only (in `assets/css/`, use `.vt-` prefix)
- JavaScript = behavior only (in `assets/js/`)

**NO inline `<script>` or `<style>` blocks** unless trivial (3-5 lines max)

**Validation & Sanitization**:
- Sanitizers return clean values: `vt_service('validation.sanitizer')->textField($input)`
- Validators return arrays with `['value', 'is_valid', 'errors']` - use ONLY in templates/controllers
- Manager classes receive pre-sanitized data, never call validators
- Flow: User Input → Template validates/sanitizes → Manager receives clean data → Database

**PHP Standards**:
- PHP 8.1+ features, strict typing
- PSR-12 compliance
- camelCase for methods/functions
- Escape all output with `htmlspecialchars()`
- Prepared statements for all queries
- CSRF tokens in all forms
- **NEVER use `error_log()` function - write to `debug.log` for debugging**

**Database**:
- `config/schema.sql` is single source of truth
- Use PDO with prepared statements
- Use transactions for multi-step operations
- All table names prefixed with `vt_`

**Security**:
- All input validated/sanitized
- All output escaped
- CSRF protection via `vt_service('security.service')->verifyNonce()`
- Passwords use `password_hash()` and `password_verify()`

## File Structure

```
social_elonara/
├── index.php                    # Application entry point
├── includes/
│   ├── bootstrap.php            # Initializes application
│   ├── Container.php            # DI container
│   ├── class-router.php         # Routing system
│   ├── class-pages.php          # Page controllers
│   ├── class-*.php              # Legacy static classes
│   ├── Database/                # Modern DB classes
│   ├── Http/                    # Modern HTTP classes
│   ├── Auth/                    # Modern auth classes
│   ├── Security/                # Modern security classes
│   ├── Validation/              # Modern validation classes
│   ├── Embed/                   # URL embed services
│   └── Image/                   # Image processing
├── templates/
│   ├── base/                    # Base page templates
│   ├── partials/                # Reusable components
│   └── *-content.php            # Page-specific content
├── assets/
│   ├── css/                     # All stylesheets (.vt- prefixed)
│   └── js/                      # All JavaScript modules
├── config/
│   ├── schema.sql               # Database schema (source of truth)
│   ├── database.php             # DB credentials (gitignored)
│   ├── security_salts.php       # Security salts
│   └── migrations/              # Incremental SQL migrations
├── dev/
│   ├── doctrine/                # XML coding standards
│   └── scripts/                 # Development utilities
└── uploads/                     # User-uploaded files
```

## Key Classes & Their Responsibilities

### Legacy Static Classes
- `VT_Router` - URL routing and dispatching
- `VT_Pages` - Page rendering controllers
- `VT_Auth` - Legacy authentication (use `auth.service` for new code)
- `VT_Database` - Legacy database (use `database.connection` for new code)
- `VT_Config` - Application configuration
- `VT_Security` - Legacy security (use `security.service` for new code)
- `VT_Event_Manager` - Event business logic
- `VT_Community_Manager` - Community business logic
- `VT_Conversation_Manager` - Conversation business logic

### Modern Service Classes
- `VT_Database_Connection` - PDO database connection
- `VT_Database_QueryBuilder` - Fluent query builder
- `VT_Http_Request` - HTTP request abstraction
- `VT_Http_Response` - HTTP response handling
- `VT_Auth_AuthenticationService` - Modern authentication
- `VT_Security_SecurityService` - CSRF and security
- `VT_Validation_InputSanitizer` - Input sanitization
- `VT_Validation_ValidatorService` - Input validation
- `VT_Embed_EmbedService` - URL embed processing
- `VT_Image_ImageService` - Image handling

### AJAX Handlers
- `VT_Event_Ajax_Handler` - Event AJAX endpoints
- `VT_Community_Ajax_Handler` - Community AJAX endpoints
- `VT_Conversation_Ajax_Handler` - Conversation AJAX endpoints

## Common Patterns

### Adding a New Page
1. Add route in `VT_Router::registerDefaultRoutes()`
2. Add method in `VT_Pages` class
3. Create content template in `templates/`
4. Use existing base template or create new one

### Adding a New API Endpoint
1. Add route with `/api/` prefix in router
2. Create/update AJAX handler class
3. Return JSON via `VT_Router::jsonResponse($data, $status)`

### Creating a Form
1. Use CSRF token: `vt_service('security.service')->nonce('action_name')`
2. Verify on submit: `vt_service('security.service')->verifyNonce($_POST['nonce'], 'action_name')`
3. Sanitize inputs: `vt_service('validation.sanitizer')->textField($input)`
4. Pass clean data to manager class

### Database Queries
Modern approach:
```php
$db = vt_service('database.connection');
$stmt = $db->prepare("SELECT * FROM vt_events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();
```

Legacy approach (still used in many places):
```php
$db = VT_Database::getInstance();
$result = $db->prepare("SELECT * FROM vt_events WHERE id = ?");
$result->execute([$id]);
$event = $result->fetch();
```

## Development Workflow

1. **Never modify files** without confirming plan first
2. **Check dev/doctrine/** standards before writing code
3. **Respect the hybrid architecture** - don't mix patterns
4. **Test database connection** before schema changes
5. **Export schema.sql** after any database modifications
6. **Run tests** via `./test.sh` before committing
7. **Syntax check** PHP files with `php -l filename.php`

## Important Notes

- Application runs on PHP 8.1+ (currently 8.4 in development)
- Web server: Apache with mod_rewrite or PHP built-in server for development
- Database: MySQL 5.7+ / MariaDB
- NO WordPress - this is a custom PHP application
- The `dev/` directory is gitignored in production
- Background PHP servers may be running - check with `/bashes` command
