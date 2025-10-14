# Elonara Social Changelog

## 2025-10-02 - Database Schema: Standardize on 'privacy' field

**Status:** ✅ Complete (Local and Production)

**Changes:**
- Removed redundant `visibility` column from `communities` table
- Standardized on `privacy` field throughout codebase
- Updated all templates, managers, and services to use `privacy` instead of `visibility`

**Commits:**
- `6b7004b` - Remove visibility column from schema definition
- `4ad62b7` - Add database migration to drop visibility column
- `7e17e47` - Standardize on 'privacy' field for community privacy settings

**Migration Completed:**
- Local: ✅ Column dropped, all communities have valid privacy values
- Production: ✅ Column dropped, system functional

**Files Changed:**
- `config/schema.sql` - Removed visibility column and index
- `includes/class-community-manager.php` - Use privacy field
- `includes/class-community-ajax-handler.php` - Use privacy field
- `includes/class-conversation-feed.php` - Use privacy field
- `includes/class-conversation-manager.php` - Use privacy field
- `includes/class-event-manager.php` - Use privacy field
- `includes/class-pages.php` - Use privacy field
- `includes/class-personal-community-service.php` - Use privacy field
- `includes/class-search-api.php` - Use privacy field
- `includes/class-circle-scope.php` - Use privacy field
- `templates/communities-content.php` - Use privacy field
- `templates/create-community-content.php` - Use privacy field
- `templates/manage-community-content.php` - Use privacy field
- `templates/single-community-content.php` - Use privacy field

**Database Schema:**
- Before: `privacy` (varchar) + `visibility` (enum) - redundant
- After: `privacy` (varchar) only - single source of truth
