# Test Coverage Analysis — AdlairePlatform

## Current State

**Test coverage: 0%.** The codebase has **no automated tests** of any kind — no PHPUnit, no Jest, no CI/CD pipeline, no test configuration files. The entire application (~4,200 lines across PHP and JavaScript) is untested.

---

## Recommended Test Infrastructure

### PHP (Backend)
- **Framework:** [PHPUnit](https://phpunit.de/) (standard for PHP testing)
- **Setup:** Add `composer.json` with `phpunit/phpunit` as a dev dependency
- **Config:** `phpunit.xml` at the project root

### JavaScript (Frontend)
- **Framework:** [Jest](https://jestjs.io/) or [Vitest](https://vitest.dev/) for unit tests
- **Setup:** Add `package.json` with the test runner as a dev dependency
- **Note:** The JS files use ES5/vanilla JS with no module system, so tests may need a DOM mock (e.g., jsdom)

---

## Priority Areas for Testing

### Priority 1 — Security-Critical (High Impact, High Risk)

These functions protect the application from attacks. Bugs here have severe consequences.

#### 1. Authentication & Login (`index.php:258-281`)
- `login()` — password verification, session creation, password change flow
- `check_login_rate()` — rate limiting (5 failures → 15-min lockout)
- `record_login_failure()` — failure counting and lockout timing
- `clear_login_rate()` — rate limit reset after successful login

**Test cases needed:**
- Correct password grants access and sets `$_SESSION['l'] = true`
- Wrong password does NOT grant access and records failure
- 5 consecutive failures trigger 15-minute lockout
- Lockout expires after 15 minutes
- Successful login clears failure history
- Password change requires correct old password first
- Session is regenerated on login (session fixation prevention)

#### 2. CSRF Protection (`index.php:492-506`)
- `csrf_token()` — token generation
- `verify_csrf()` — token validation

**Test cases needed:**
- Token is generated and stored in session
- Valid token passes verification
- Missing token is rejected (403)
- Wrong token is rejected (403)
- Empty session CSRF + empty POST CSRF does NOT pass (hash_equals bypass)

#### 3. Input Validation & Sanitization
- `edit()` (`index.php:214-241`) — fieldname regex validation
- `h()` (`index.php:156-158`) — HTML escaping
- `getSlug()` (`index.php:138-140`) — slug generation
- `host()` (`index.php:508-519`) — URL parsing and stripping of dangerous characters

**Test cases needed:**
- `edit()` rejects fieldnames with special characters (path traversal: `../`, etc.)
- `edit()` rejects requests without session
- `h()` properly escapes `<`, `>`, `"`, `'`, `&`
- `getSlug()` lowercases and replaces spaces with hyphens
- `getSlug()` handles multibyte (UTF-8) strings correctly
- `host()` strips `index.php`, quotes, angle brackets, and other dangerous chars

#### 4. Image Upload Validation (`index.php:170-212`)
- `upload_image()` — file type checking, size limit, random filename generation

**Test cases needed:**
- Rejects uploads from unauthenticated users (401)
- Rejects files larger than 2MB
- Rejects non-image MIME types (e.g., `application/php`, `text/html`)
- Accepts JPEG, PNG, GIF, WebP
- Generated filenames are random (not user-controlled)
- CSRF token is verified

### Priority 2 — Data Integrity (Medium Impact)

#### 5. JSON Read/Write (`index.php:333-351`)
- `json_read()` — file reading with fallback to empty array
- `json_write()` — atomic writes with `LOCK_EX`

**Test cases needed:**
- `json_read()` returns `[]` for nonexistent files
- `json_read()` returns `[]` for corrupted/non-JSON files
- `json_read()` correctly decodes valid JSON
- `json_write()` creates file with proper JSON encoding
- `json_write()` preserves Unicode characters (`JSON_UNESCAPED_UNICODE`)
- `json_write()` handles write failures (returns 500)

#### 6. Revision System (`index.php:357-450`)
- `save_revision()` — creates timestamped revision files
- `prune_revisions()` — enforces 30-revision limit
- `handle_revision_action()` — list/restore revision endpoints

**Test cases needed:**
- Revisions are saved with correct timestamp and content
- Pruning removes oldest revisions when count exceeds `AP_REVISION_LIMIT` (30)
- Listing returns revisions in reverse chronological order
- Restoring a revision updates the page content
- Preview mode (`preview=1`) does NOT save changes
- Invalid fieldnames/revision names are rejected
- Nonexistent revisions return 404

#### 7. Content Editing (`index.php:214-241`)
- `edit()` — saving settings vs. page content

**Test cases needed:**
- Settings keys (`title`, `description`, etc.) are saved to `settings.json`
- Non-settings keys are saved to `pages.json`
- Content is trimmed before saving
- Revision is created when saving page content
- Unauthenticated edits are rejected (401)

### Priority 3 — Infrastructure (Medium Impact)

#### 8. ThemeEngine (`engines/ThemeEngine.php`)
- `ThemeEngine::load()` — theme loading with fallback
- `ThemeEngine::listThemes()` — directory listing

**Test cases needed:**
- Valid theme name loads the correct `theme.php`
- Invalid theme name (special chars) falls back to `AP-Default`
- Nonexistent theme falls back to `AP-Default`
- `listThemes()` returns directory names from `themes/`

#### 9. UpdateEngine (`engines/UpdateEngine.php`)
- `check_environment()` — checks for ZipArchive, writable dirs, disk space
- `check_update()` — GitHub API integration with caching
- `backup_current()` — full backup creation
- `prune_old_backups()` — keeps only 5 most recent backups
- `apply_update()` — download, extract, overwrite flow
- `rollback_to_backup()` — restore from backup
- `delete_backup()` — backup removal

**Test cases needed:**
- `check_environment()` reports correct capabilities
- `check_update()` uses cache when not expired
- `check_update()` fetches from GitHub when cache is expired
- `check_update()` handles rate limiting (403/429) gracefully
- `backup_current()` excludes `data/`, `backup/`, `.git/`
- `prune_old_backups()` keeps exactly `AP_BACKUP_GENERATIONS` (5)
- `apply_update()` validates URL is from GitHub domains only
- `rollback_to_backup()` rejects names with non-numeric characters
- `delete_backup()` validates backup name format

#### 10. Migration (`index.php:452-490`)
- `migrate_from_files()` — two-phase data migration

**Test cases needed:**
- Phase 1: flat files in `files/` are migrated to JSON in `data/`
- Phase 2: `data/*.json` files are moved to `data/settings/` and `data/content/`
- Migration is skipped if target files already exist
- Password hashes are preserved during migration

### Priority 4 — JavaScript (Frontend)

#### 11. WYSIWYG Editor (`engines/JsEngine/wysiwyg.js` — 2,514 lines)
This is the largest single component. Key areas:
- HTML sanitizer (whitelist-based) — **security-critical**
- Block creation/deletion/reordering
- Image insertion and resizing
- Auto-save mechanism
- Undo/redo stack
- Slash-command menu

#### 12. Updater UI (`engines/JsEngine/updater.js`)
- Update check and display
- Backup list rendering
- Rollback trigger

#### 13. In-Place Editor (`engines/JsEngine/editInplace.js`)
- Click-to-edit activation
- Auto-save on blur via fetch

---

## Coverage Gap Summary

| Area | Lines | Tests | Risk |
|------|-------|-------|------|
| Authentication & Login | ~50 | 0 | **Critical** |
| CSRF Protection | ~15 | 0 | **Critical** |
| Input Validation | ~30 | 0 | **Critical** |
| Image Upload | ~45 | 0 | **High** |
| JSON I/O | ~20 | 0 | **High** |
| Revision System | ~95 | 0 | **High** |
| Content Editing | ~30 | 0 | **Medium** |
| ThemeEngine | ~24 | 0 | **Medium** |
| UpdateEngine | ~330 | 0 | **Medium** |
| Migration | ~40 | 0 | **Medium** |
| WYSIWYG Editor (JS) | ~2,514 | 0 | **Medium** |
| Other JS | ~330 | 0 | **Low** |

---

## Recommended Implementation Order

1. **Set up PHPUnit** — add `composer.json`, `phpunit.xml`, create `tests/` directory
2. **Refactor for testability** — extract pure functions from `index.php` into separate files (e.g., `engines/AuthEngine.php`, `engines/ContentEngine.php`) so they can be tested without booting the entire app
3. **Write security tests first** — authentication, CSRF, input validation, upload validation
4. **Write data integrity tests** — JSON I/O, revision system, content editing
5. **Write infrastructure tests** — ThemeEngine, UpdateEngine, migration
6. **Set up Jest/Vitest** — add `package.json` for JS testing
7. **Write JS tests** — prioritize the HTML sanitizer in `wysiwyg.js`, then auto-save, then block operations
8. **Add CI/CD** — GitHub Actions workflow to run PHPUnit + Jest on every push/PR
