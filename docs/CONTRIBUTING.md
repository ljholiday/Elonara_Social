# Contributing to Elonara Social

Thank you for considering a contribution to Elonara Social. This document outlines coding practices, validation patterns, architecture principles, and industry standards that guide this project.

---

## 1. Project Philosophy

Elonara Social is a custom PHP MVC application built around **Circles of Trust** anti-algorithm social filtering. It prioritizes:

- **Security first**: All input validated and sanitized at boundaries
- **Clean architecture**: Separation of concerns following industry patterns
- **Modern PHP**: PHP 8.1+ with strict types and dependency injection
- **Language separation**: PHP for logic, HTML for structure, CSS for style, JavaScript for behavior
- **Native implementation**: Custom framework without WordPress dependencies

### Core Values
- **Trust over algorithms**: Human-curated social networks, not engagement optimization
- **Privacy by design**: User control over data and visibility
- **Professional presentation**: Clean interface without emojis or decorative elements
- **Maintainability**: Code must be readable and understandable without explanation

### Two-Community Model

Every new user gets TWO communities automatically created:

1. **[Display Name] Circle** (Private Personal Community)
   - Privacy: Private (always, cannot be changed)
   - Access: Invite-only (owner controls membership)
   - Purpose: Close Circle (Circle 1) - trusted inner circle
   - Discovery: Hidden from discovery, not searchable
   - Naming: No apostrophes - "Lonn Holiday Circle" not "Lonn Holiday's Circle"

2. **[Display Name]** (Public Community)
   - Privacy: Public
   - Access: Anyone can join (instant, no approval)
   - Purpose: Public-facing content, discoverable presence
   - Discovery: Listed in community directory, searchable
   - Naming: Just the display name - "Lonn Holiday"

**Rationale**: Solves cold start problem (public discoverability) while maintaining privacy by design (invite-only inner circle).

---

## 2. Validation and Sanitization Architecture

### The Boundary Pattern (Industry Standard)

Elonara Social follows the **Trust Boundary Pattern**, a fundamental security principle outlined in OWASP's secure coding practices and common in frameworks like Laravel, Symfony, and Rails.

**Key Principle**: Validation and sanitization occur at the application boundary (where untrusted data enters), not in business logic layers.

### Three-Layer Architecture

```
┌─────────────────────────────────────────┐
│   Boundary Layer (Templates/AJAX)      │  ← Validate & Sanitize
│   - Receives untrusted user input      │
│   - Validates format and constraints   │
│   - Sanitizes for safe handling        │
└─────────────┬───────────────────────────┘
              │ Clean Data
              ↓
┌─────────────────────────────────────────┐
│   Business Logic (Managers/Services)    │  ← Trust the Data
│   - Receives pre-validated data        │
│   - Implements business rules           │
│   - NO validation/sanitization here    │
└─────────────┬───────────────────────────┘
              │ Trusted Data
              ↓
┌─────────────────────────────────────────┐
│   Data Layer (Database)                 │  ← Store Clean Data
│   - Uses prepared statements            │
│   - Handles typed data safely           │
└─────────────────────────────────────────┘
```

### Elonara Social's Validation Services

**Two distinct services with different purposes:**

#### 1. Sanitizer Service (Returns Values)
Use sanitizers to **clean data** and get the sanitized value directly:

```php
// In templates/AJAX handlers - CORRECT
$name = vt_service('validation.sanitizer')->textField($_POST['name']);
$email = vt_service('validation.sanitizer')->email($_POST['email']);
$content = vt_service('validation.sanitizer')->richText($_POST['content']);

// Pass clean data to manager
$manager->createConversation([
    'title' => $name,
    'author_email' => $email,
    'content' => $content
]);
```

#### 2. Validator Service (Returns Validation Results)
Use validators when you need **error feedback** for users:

```php
// In forms where you show validation errors - CORRECT
$emailValidation = vt_service('validation.validator')->email($_POST['email']);

if (!$emailValidation['is_valid']) {
    foreach ($emailValidation['errors'] as $error) {
        echo '<p class="error">' . esc_html($error) . '</p>';
    }
} else {
    // Use the validated value
    $cleanEmail = $emailValidation['value'];
}
```

**Critical Rule**: Validators return arrays `['value' => ..., 'is_valid' => ..., 'errors' => []]`. Never pass validator results directly to database operations.

### What NOT To Do

```php
// WRONG - In manager class
class ConversationManager {
    public function addReply($conversation_id, $data) {
        // ❌ DON'T DO THIS
        $name = vt_service('validation.validator')->textField($data['name']);
        // Returns array, not string - breaks database insert!

        $this->db->insert('replies', ['author_name' => $name]);
        // ERROR: Array to string conversion
    }
}

// CORRECT - In template (boundary)
$name = vt_service('validation.sanitizer')->textField($_POST['name']);
$manager->addReply($conversation_id, ['name' => $name]);

// CORRECT - In manager (trusts data)
class ConversationManager {
    public function addReply($conversation_id, $data) {
        // Data is already clean, just use it
        $this->db->insert('replies', ['author_name' => $data['name']]);
    }
}
```

### Industry References

This pattern is documented in:

- **OWASP Secure Coding Practices**: Input Validation at Trust Boundaries
- **Laravel Framework**: Form Requests validate at controller layer, Models trust data
- **Symfony Framework**: Form component validates, Services receive clean DTOs
- **Domain-Driven Design**: Aggregate Roots trust data from Application Services
- **Clean Architecture**: Use Cases validate, Entities trust the Use Case layer

**Key insight**: Once data passes validation at the boundary, deeper layers trust it. Re-validating in every layer introduces bugs, performance overhead, and architectural confusion.

---

## 3. Naming Conventions

### PHP Methods
- **All PHP class methods use camelCase** (PSR-1/PSR-12 standard)
- Examples:
  - `getUserProfile()` ✅ (correct)
  - `get_user_profile()` ❌ (incorrect)
  - `sendRsvpInvitation()` ✅ (correct)

### PHP Variables
- Use snake_case for local variables
- Examples:
  - `$current_user` ✅
  - `$event_data` ✅
  - `$rsvp_status` ✅

### Class Naming
- Use `VT_` prefix with underscore-separated words
- Examples:
  - `VT_Event_Manager` ✅
  - `VT_Community_Manager` ✅
  - `VT_Conversation_Manager` ✅

### File Naming
- PHP class files: `class-event-manager.php` (hyphen-separated)
- Template files: `dashboard-content.php` (hyphen-separated)

### Database Conventions
- **Table names**: Singular nouns with underscores (`vt_events`, `vt_communities`)
- **Column names**: snake_case (`event_date`, `created_at`, `display_name`)

### Username vs Display Name
- **Username**: Login identifier, unique, alphanumeric, stored in `vt_users.username`
- **Display Name**: User's preferred name, human-readable, can contain spaces

Priority order when displaying names:
1. `vt_user_profiles.display_name` (user's preferred display name)
2. `vt_users.display_name` (fallback display name)
3. `vt_users.username` (final fallback)

### Privacy Terminology
- Use **privacy** column consistently across all entities
- Values: `'public'` or `'private'`
- Applied to: Events, Communities, Conversations

---

## 4. Development Setup

### System Requirements
- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx) with mod_rewrite or equivalent
- Composer for dependency management (if used)

### Installation
1. Clone repository:
   ```bash
   git clone https://github.com/social_elonara/social_elonara.git
   cd social_elonara
   ```

2. Copy database configuration:
   ```bash
   cp config/database.php.sample config/database.php
   ```

3. Edit `config/database.php` with your credentials

4. Create database:
   ```bash
   mysql -u root -p
   CREATE DATABASE social_elonara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
   ```

5. Run installation:
   ```bash
   chmod +x install.sh
   ./install.sh
   ```

6. Configure web server to point to project root

### Database Schema Management

**Single Source of Truth**: `config/schema.sql`

- Schema exports are the authoritative definition of database structure
- `install.sh` imports schema.sql directly
- `migrate.php` is for development convenience only (not deployed)
- Before committing schema changes, export current database:
  ```bash
  mysqldump -u root --no-data --skip-add-drop-table --skip-comments social_elonara | \
    sed 's/ AUTO_INCREMENT=[0-9]*//g' > config/schema.sql
  ```

**Never**:
- Manually edit production databases
- Rely on migrate.php for deployments
- Commit schema.sql without testing clean install

---

## 5. Code Organization Standards

### Language Separation (Strict)

**PHP** - Server-side logic only
- Lives in: `includes/`, `templates/`
- Handles: Business logic, data access, template rendering
- NO HTML structure, NO inline JavaScript, NO CSS rules

**HTML** - Structure and semantics only
- Lives in: `templates/`
- Handles: Document structure, semantic markup
- Minimal inline PHP for rendering only (loops, conditionals)

**CSS** - Presentation only
- Lives in: `assets/css/`
- All classes use `.vt-` prefix
- NO inline styles in templates
- NO style attributes except for user-generated dynamic values

**JavaScript** - Client behavior only
- Lives in: `assets/js/`
- Organized by feature: `conversations.js`, `communities.js`, `modal.js`
- NO inline `<script>` blocks longer than 3 lines
- Use event delegation, not inline `onclick` handlers

### File Structure

```
social_elonara/
├── includes/              # PHP classes and business logic
│   ├── Auth/             # Authentication services
│   ├── Database/         # Database services
│   ├── Security/         # Security services
│   ├── Validation/       # Validation & sanitization
│   ├── class-*.php       # Legacy managers (being modernized)
│   └── bootstrap.php     # Application initialization
├── templates/            # HTML templates
│   ├── base/            # Layout templates (two-column, form, page)
│   ├── partials/        # Reusable components
│   └── *-content.php    # Page content templates
├── assets/
│   ├── css/             # Stylesheets (.vt- prefix required)
│   └── js/              # JavaScript modules
├── config/              # Configuration files
│   ├── schema.sql       # Database schema (source of truth)
│   └── database.php     # Database credentials (not in git)
├── dev/doctrine/        # Development standards (XML charters)
└── .claude/             # Claude Code workspace (gitignored)
```

### Manager vs Service Pattern

**Managers** (Legacy, being phased out):
- Handle specific entity CRUD: `VT_Conversation_Manager`, `VT_Event_Manager`
- Direct database access via `$this->db`
- Being modernized to use Services

**Services** (Modern):
- Single responsibility: `AuthenticationService`, `SecurityService`
- Dependency injection via constructor
- Accessed via: `vt_service('auth.service')`
- Return typed values or throw exceptions

---

## 6. URL Routing Standards

### Core Principles
1. **Predictable**: URLs follow consistent patterns across all resources
2. **Readable**: Use slugs for public-facing URLs, not numeric IDs
3. **RESTful**: Follow REST conventions for resource actions
4. **Hierarchical**: Express resource relationships through URL structure
5. **Secure**: Never expose internal database IDs in public URLs

### Resource Identifier Standards
- **Internal Operations**: Use numeric auto-increment IDs
- **Public URLs**: Use human-readable slugs
- **Slug Format**: lowercase, hyphenated, derived from titles/names
  - Example: "Beach Party 2024" → "beach-party-2024"

### URL Structure Patterns

**Collection Resources:**
```
GET    /resources              # List all resources
POST   /resources              # Create new resource
GET    /resources/create       # Show creation form
```

**Individual Resources:**
```
GET    /resources/{slug}       # View single resource
PUT    /resources/{slug}       # Update resource (API)
DELETE /resources/{slug}       # Delete resource (API)
```

**Resource Actions:**
```
GET    /resources/{slug}/edit     # Show edit form
POST   /resources/{slug}/edit     # Process edit form
GET    /resources/{slug}/manage   # Management interface
POST   /resources/{slug}/manage   # Process management actions
```

### Elonara Social Resource Mappings

**Events:**
```
GET    /events                    # Events list page
GET    /events/create            # Create event form
POST   /events/create            # Process event creation
GET    /events/{slug}            # Single event page
GET    /events/{slug}/edit       # Edit event form
GET    /events/{slug}/manage     # Manage event (host only)
```

**Communities:**
```
GET    /communities                    # Communities list page
GET    /communities/create            # Create community form
POST   /communities/create            # Process community creation
GET    /communities/{slug}            # Single community page
GET    /communities/{slug}/edit       # Edit community form
GET    /communities/{slug}/manage     # Manage community (admin only)
GET    /communities/{slug}/members    # Members list
```

**Conversations:**
```
GET    /conversations                 # Conversations list page
GET    /conversations/create         # Create conversation form
POST   /conversations/create         # Process conversation creation
GET    /conversations/{slug}         # Single conversation page
POST   /conversations/{slug}/reply   # Add reply
```

**Authentication:**
```
GET    /login                        # Login form
POST   /login                        # Process login
GET    /logout                       # Logout action
GET    /register                     # Registration form
POST   /register                     # Process registration
```

---

## 7. Database Architecture

### Core Tables

#### Events System
- **vt_events**: Event data (title, description, date, time, venue)
- **vt_guests**: RSVP and guest management with 32-character tokens
- **vt_event_invitations**: Event invitation system
- **vt_event_rsvps**: Modern RSVP flow with accessibility support

#### Communities System (Circles of Trust)
- **vt_communities**: Community management with privacy controls
  - `privacy` enum('public','private') - Core circles functionality
  - `type` varchar(50) - standard, personal, food, hobby, etc.
  - `personal_owner_user_id` - For personal communities (circles)
- **vt_community_members**: Membership with roles (admin, member) and status
- **vt_community_events**: Links communities and events
- **vt_community_invitations**: Community invitation system

#### Conversations System
- **vt_conversations**: Discussion threads linked to events/communities
- **vt_conversation_replies**: Threaded replies with depth tracking
- **vt_conversation_follows**: User subscriptions
- **vt_conversation_topics**: Categorization system

#### User System
- **vt_users**: LAMP user authentication (login, email, password_hash)
- **vt_user_profiles**: Full hosting functionality and preferences
- **vt_member_identities**: AT Protocol integration

### Key Business Rules

**RSVP Status**: `pending`, `yes`, `no`, `maybe`
**Event Status**: `active`, `cancelled`, `completed`
**Community Privacy**: `public`, `private`
**Token System**: 32-character tokens for invitations

---

## 8. Security Requirements

### Input Validation Checklist

At every boundary (template, AJAX handler, API endpoint):

```php
// ✓ Verify authentication
if (!vt_service('auth.service')->isLoggedIn()) {
    VT_Router::redirect('/login');
}

// ✓ Verify authorization
if (!$manager->canEdit($item_id, $current_user_id)) {
    http_response_code(403);
    die('Access denied');
}

// ✓ Verify CSRF token
if (!vt_service('security.service')->verifyNonce($_POST['nonce'], 'action_name')) {
    VT_Ajax::sendError('Security check failed');
}

// ✓ Sanitize all inputs
$title = vt_service('validation.sanitizer')->textField($_POST['title']);
$content = vt_service('validation.sanitizer')->richText($_POST['content']);
```

### Output Escaping

Always escape output based on context:

```php
// HTML context
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
echo vt_service('validation.validator')->escHtml($data);

// HTML attribute context
echo vt_service('validation.validator')->escAttr($data);

// URL context
echo vt_service('validation.validator')->escUrl($url);
```

### Database Security

```php
// ✓ ALWAYS use prepared statements
$db->prepare("SELECT * FROM vt_users WHERE id = %d", $user_id);

// ✓ Use service methods
$auth = vt_service('auth.service');
$user_id = $auth->getCurrentUserId();

// ✗ NEVER concatenate SQL
$query = "SELECT * FROM users WHERE id = " . $_GET['id']; // SQL injection!
```

---

## 9. Testing Before Pull Requests

### Code Quality Checks

```bash
# Check for unprefixed CSS classes
grep -rn "class=\"[^\"]*\"" templates/ | grep -v "vt-" | head -20

# Check for inline styles (should be minimal)
grep -rn "style=" templates/ | wc -l

# Check for emojis in code (should return nothing)
grep -P "[\\x{1F300}-\\x{1F9FF}]" includes/ templates/

# Check for debug code
grep -rn "var_dump\|print_r\|dd(" includes/ templates/

# Check for error_log() usage (forbidden)
grep -rn "error_log(" includes/ templates/

# Check error log for issues
tail -50 error.log
```

### Functional Testing

- [ ] Clean database install works (`./install.sh`)
- [ ] User registration and login functions
- [ ] Creating events/communities/conversations works
- [ ] Circles of Trust filtering returns correct results
- [ ] AJAX operations return valid JSON
- [ ] File uploads work with correct permissions
- [ ] Mobile responsive layouts display correctly
- [ ] No JavaScript errors in browser console
- [ ] No PHP errors in error.log

---

## 10. Git Workflow

### Branch Naming

```bash
feature/circles-of-trust-filtering
fix/conversation-reply-validation
refactor/modernize-auth-service
docs/update-contributing-guide
```

### Commit Messages

```bash
# Good commits
git commit -m "Add Circles of Trust conversation filtering

Implement VT_Conversation_Feed::list() with inner/trusted/extended
circle parameters. Updates AJAX handler and JavaScript to support
dynamic filtering."

# Bad commits
git commit -m "fix stuff"           # Too vague
git commit -m "WIP"                 # Not descriptive
git commit -m "Update files"        # Says nothing
```

### Pull Request Process

1. **Create feature branch** from `main`
2. **Make changes** following these standards
3. **Test thoroughly** (see section 9)
4. **Update documentation** if needed
5. **Commit with descriptive messages**
6. **Push and create PR** with:
   - Clear description of changes
   - Why the change was needed
   - How you tested it
   - Any breaking changes

---

## 11. Common Patterns

### Creating a New Feature

1. **Manager/Service** (Business Logic):
```php
class VT_Thing_Manager {
    private $db;

    public function __construct() {
        $this->db = VT_Database::getInstance();
    }

    // Data is already clean - trust it
    public function createThing($data) {
        return $this->db->insert('things', [
            'title' => $data['title'],
            'user_id' => $data['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
```

2. **Template** (Boundary):
```php
// templates/create-thing-content.php
$manager = new VT_Thing_Manager();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify security
    if (!vt_service('security.service')->verifyNonce($_POST['nonce'], 'create_thing')) {
        $errors[] = 'Security check failed';
    }

    // Sanitize inputs
    $title = vt_service('validation.sanitizer')->textField($_POST['title']);

    // Validate
    if (empty($title)) {
        $errors[] = 'Title is required';
    }

    // If valid, create
    if (empty($errors)) {
        $result = $manager->createThing([
            'title' => $title,
            'user_id' => vt_service('auth.service')->getCurrentUserId()
        ]);

        if ($result) {
            VT_Router::redirect('/things');
        }
    }
}
?>
<form method="post">
    <?php echo vt_service('security.service')->nonceField('create_thing'); ?>
    <input type="text" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
    <button type="submit">Create</button>
</form>
```

3. **JavaScript** (Behavior):
```javascript
// assets/js/things.js
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Feature-specific JavaScript
    });
})();
```

4. **CSS** (Presentation):
```css
/* assets/css/style.css */
.vt-thing-card {
    padding: var(--vt-space-md);
    background: var(--vt-surface);
}
```

---

## 12. Key Lessons from Migration

### Core Infrastructure

1. **No Circular Dependencies**: Database and Config classes must be independent
2. **MySQL Socket Paths**: Check actual MySQL configuration before assuming standard connections
3. **Session Management**: Use `session_status()` check before `session_start()`
4. **Absolute Paths Always**: Use `VT_ROOT_DIR . '/path'` for all file includes
5. **Method Signatures**: Verify static vs instance methods before calling

### Database Operations

1. **insert_id Timing**: Retrieve immediately after INSERT, before any other queries
2. **WordPress Placeholder Conversion**: Convert %d, %s, %f to ? in prepare() method
3. **PDO Statement Handling**: Methods must handle both raw queries and PDOStatement objects
4. **Database Prefix**: Make VT_Database->prefix property public for template access

### Testing and Validation

1. **Test Incrementally**: Verify each component works before building on it
2. **Check Dependencies**: Verify all classes and methods exist before using
3. **No Assumptions**: Don't assume relative paths, method names, or column names

---

## 13. Future Roadmap

### Current Phase: User Testing & Validation
- Testing core functionality with real data
- Community creation and membership
- Event creation and RSVP
- Conversations and Circles of Trust filtering

### Planned Features

**Email System Integration** (High Priority)
- SMTP configuration
- RSVP confirmations and reminders
- Community invitations
- Password reset functionality

**Bluesky Social Integration** (High Priority)
- Bluesky API integration for follower retrieval
- Social invitation system
- Cross-platform identity verification

**Enhanced Media System** (Medium Priority)
- Conversation reply images
- Community and event cover images
- Image optimization and processing
- oEmbed integration for link previews

**Modular Invitation System** (Medium Priority)
- Unified invitation/request/join/RSVP system
- Join request approval workflow
- Reusable UI components
- Consistent email templates

See `dev/consolidate-dev/DEPLOYMENT_ROADMAP.md` and `FUTURE_MODULAR_INVITATION_SYSTEM.md` for detailed planning.

---

## 14. Code Review Standards

### For Contributors

Before submitting PR, verify:
- [ ] No validators called in manager classes
- [ ] All CSS classes use `.vt-` prefix
- [ ] No inline styles (except dynamic user values)
- [ ] No JavaScript longer than 3 lines in templates
- [ ] All user input sanitized at boundaries
- [ ] All output escaped appropriately
- [ ] CSRF tokens on all state-changing forms
- [ ] No debug code (var_dump, print_r, error_log)
- [ ] No emojis in code or UI
- [ ] Tests pass, no errors in logs

### For Reviewers

Check:
- [ ] Architecture: Boundary pattern followed correctly
- [ ] Security: Input validated, output escaped, nonces verified
- [ ] Separation: PHP/HTML/CSS/JS properly separated
- [ ] Performance: No N+1 queries, appropriate indexes
- [ ] Standards: Follows dev/doctrine/*.xml guidelines
- [ ] Testing: Evidence of thorough testing provided

---

## 15. Getting Help

- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: Questions about architecture and patterns
- **Pull Requests**: Code contributions with clear descriptions
- **Documentation**: Check `dev/doctrine/*.xml` for specific guidelines

---

**Thank you for contributing to Elonara Social and helping build an anti-algorithm social platform!**
