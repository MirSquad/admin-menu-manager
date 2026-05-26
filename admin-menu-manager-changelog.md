---
title: "Admin Menu Manager — Changelog"
doc_type: changelog
project: admin-menu-manager
created: 2026-03-29
updated: 2026-05-25
status: active
summary: "Full session-by-session history of changes to the Admin Menu Manager plugin."
tags: [wordpress, plugin, admin, sidebar, menu]
blog_candidate: false
---

# Admin Menu Manager — Changelog

---

## Session 1 — 2026-03-29

### v1.0.0 — initial build

`admin-menu-manager.php` created as a single-file plugin with:
- Settings page at Settings > Menu Manager
- Table-based UI with number fields for ordering, hide checkboxes, and a group assignment dropdown
- Custom groups managed as a separate section above the item table
- URL-safe base64 encoding per POST key to handle slugs with special characters
- `apply_config()` hook at `admin_menu` priority 999 to rebuild `$menu` from saved config

**Decisions made:**
- Single-file plugin (no build, no dependencies beyond WP core)
- URL-safe base64 per POST key as the encoding strategy for special-character slugs
- `admin_menu` priority 999 to ensure all plugins register before snapshot
- Applies to `manage_options` users only

**Dead ends:** None in v1 — first build.

---

### v2.0.0 — rebuild following user feedback

**Changes to `admin-menu-manager.php`:**
- Replaced number-field ordering with jQuery UI Sortable drag-and-drop
- Moved groups out of a separate section and into the main item list as inline rows
- Added "Nest under item" option to the parent dropdown
- Replaced per-key URL-safe base64 encoding with a single JSON payload (`mm_payload`) serialized on form submit
- Group IDs now generated client-side via `Date.now()`
- Add/rename/delete group operations update all parent `<select>` dropdowns live without page reload

**Decisions made:**
- JSON payload over per-key encoding — cleaner for the richer v2 data structure. See Decisions Log.
- Groups inline in the list — groups need to be orderable relative to other items.
- `Date.now()` for group IDs — simple, unique enough, no server dependency.

**Dead ends:** None — direct responses to user feedback.

---

## Session 2 — 2026-03-29

### v2.1.0 — polish and naming consistency

**Changes to `admin-menu-manager.php`:**
- Added `plugin_action_links_` filter hook — Settings link in the Plugins list page
- Renamed "Menu Manager" → "Admin Menu Manager" in both `add_options_page()` arguments
- Version bumped 2.0.0 → 2.1.0

**Decisions made:**
- `plugin_action_links_` + `plugin_basename(__FILE__)` is the standard WP pattern. `array_unshift()` puts Settings before Deactivate.
- "Admin Menu Manager" everywhere to match the plugin header.

**Dead ends:** None.

---

## Session 3 — 2026-03-29

### v2.2.0 — reset to defaults

**Changes to `admin-menu-manager.php`:**
- Added reset handler in `handle_save()` — checks for `wpa_reset` POST flag, calls `delete_option()`, redirects with `?reset=1`
- Added reset notice in `show_notice()` — yellow warning banner confirming the reset
- Added Reset to Defaults section in `render_page()` — separate `<form>` below the Save button with a JS confirm dialog

**Decisions made:**
- Separate `<form>` for reset rather than a flag in the main save form — keeps the save and reset flows completely independent and avoids accidental resets.

---

### v2.3.0 — three-bug fix: groups, items disappearing, submenu rendering

**Root cause:** `render_page()` was reading from the already-modified `$menu` global. After `apply_config()` ran, items moved into groups were removed from `$menu`, and group entries appeared with non-`wpa_mm_grp_` slugs that the filter didn't catch.

**Changes to `admin-menu-manager.php`:**
- Added `$orig_snap` class property — `apply_config()` now stores a snapshot of the original `$menu` (with sub-item counts) before modifying it
- `render_page()` now reads from `$orig_snap` instead of the current `$menu`, so all items always appear in the settings list regardless of where they're assigned
- `n_sub` now stored in the snap rather than recounted at render time
- Group submenu now stored under `$first_url` (the first child's URL) instead of `$gslug` — WordPress looks for `$submenu[$menu[n][2]]`, so it must match the URL in the `$menu` entry. See Decisions Log.

**Dead ends:**
- Tried filtering groups by `item[5]` (the CSS ID field) — unreliable. Pre-storing the snapshot before modification is the clean solution.

---

### v2.4.0 — fix 404s on nested sub-items from plugins like All-in-One WP Migration

**Root cause:** When a plugin's sub-items (e.g. All-in-One's Import, Backups) are flattened into a new parent, bare page slugs like `ai1wm_import` get rendered as `{new-parent}.php?page=ai1wm_import` instead of `admin.php?page=ai1wm_import`, causing 404s.

**Changes to `admin-menu-manager.php`:**
- Added `normalize_menu_url()` private method — converts bare slugs to `admin.php?page=SLUG`; passes through URLs that already contain `.php` or `://`
- Applied to both the item itself and all its sub-items in both flatten paths (item-under-item and group)

**Why Export worked while others didn't:** Export's slug was already stored as a full URL (`admin.php?page=ai1wm_export`). The others were bare slugs.

---

### v2.5.0 — fix "not allowed" access errors on nested sub-pages

**Root cause:** After flattening, `unset($submenu[$cs])` was removing the original submenu entries. WordPress's `user_can_access_admin_page()` walks all of `$submenu` to validate access — without those entries, WP denied access even when the URL was correct.

**Changes to `admin-menu-manager.php`:**
- Removed both `unset($submenu[$cs])` calls in the flatten paths
- Replaced with explanatory comments documenting why they must stay

**Why orphaned entries don't cause duplicate display:** Without a top-level `$menu` entry pointing to them, WordPress never renders them in the sidebar.

---

### v2.6.0 — performance improvements

**Changes to `admin-menu-manager.php`:**
- `update_option()` now passes `autoload = false` — config was being loaded on every frontend page request
- Class only instantiates inside `is_admin()` — no frontend overhead at all
- Added `$by_parent` pre-index and `$configured_set` hash in `apply_config()` — eliminated O(n²) child-finding loops
- `get_config()` now caches result in `$this->config` — avoids calling `get_option()` more than once per request

---

### v2.7.0 — security hardening

**Changes to `admin-menu-manager.php`:**
- Removed duplicate `json_decode` block in `handle_save()` — dead code from prior editing
- Added 200 KB payload size check before `json_decode`
- Added `mb_substr()` length caps: group titles capped at 200 chars, slugs at 500 chars
- `esc_attr()` added to `self::ACTION` in both form `value` attributes
- `esc_attr()` added to `$row_border` in inline `style` attributes

---

### v2.8.0 — dead code removal and general cleanup

**Changes to `admin-menu-manager.php`:**
- Removed dead `$item_cfg` build loop in `apply_config()` — made redundant by v2.6.0 performance refactor but never deleted
- Standardised nonce handling: main save form now uses `wp_nonce_field()` instead of manual `wp_create_nonce()` + hand-rolled input
- Removed single-use local variables `$save_url` and `$nonce` — inlined
- Replaced ~12 `isset($x) ? $x : $y` patterns with `$x ?? $y` null coalescing throughout
- Fixed indentation damage in both `$subs[]` array blocks from prior awk-based editing sessions

**Dead ends:** None — pure cleanup session.

**What's next:**
- Submenu ordering (nested items currently always append after parent's original sub-items)
- Active state highlighting for custom groups
- Per-role configuration

---

## Session 4 — 2026-05-25

### WP.org readiness and code quality audit

Full pass for WP.org submission readiness, i18n completeness, and security hardening. No new features — all changes are code quality and compliance.

**Plugin header — missing fields added:**
- `Plugin URI`, `Author URI`, `License URI`, `Text Domain`, `Domain Path`
- `Requires at least: 5.0`, `Requires PHP: 7.4`
- Version constant `WPA_MM_VERSION` defined; text domain loaded via `add_action('init', ...)` + `load_plugin_textdomain()`

**i18n — all user-facing strings wrapped:**
- `render_page()`: every visible string now uses `esc_html_e()`, `esc_attr_e()`, `wp_kses()`, or `_n()` with the `admin-menu-manager` text domain
- `action_links` filter: `esc_html__( 'Settings', 'admin-menu-manager' )`
- `wp_die()` calls: updated to `esc_html__()` + `['response' => 400]` HTTP status code

**Inline JavaScript i18n — `wpaMM` object added:**
- PHP-generated `var wpaMM = { ... }` object output before the main `<script>` block
- All hardcoded English strings in `buildGroupRow()` and `confirm()` dialogs replaced with `wpaMM.*` references
- All values escaped with `esc_js()` at the PHP output point

**`show_notice()` — phpcs:ignore added:**
- `$_GET['updated']` read-only flag now has `// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by wp_redirect() after save.`

**`plugin_row_meta` filter added:**
- Slug `admin-menu-manager` is taken on WP.org — filter removes the "View details" link (which would open a different plugin's directory listing) and adds a "Visit plugin site" link to miriamschwab.me instead
- **Slug must be renamed before WP.org submission** — touches folder name, text domain, and option keys

**New files created:**
- `uninstall.php` — deletes `wpa_mm_config` option on plugin deletion; guarded with `WP_UNINSTALL_PLUGIN` check
- `readme.txt` — full WP.org-format file with description, install steps, FAQ, changelog, and upgrade notices
- `languages/` directory — placeholder for future .pot file and translations

**`.gitignore` updated:**
- Replaced single-name pillar doc patterns with wildcards (`*-handoff.md`, `*-changelog.md`, etc.) so prefixed filenames are caught

### Decisions made

- **Slug rename deferred:** Renaming is a significant change touching folder, text domain, and all option keys. Deferred until WP.org submission is imminent. `plugin_row_meta` filter handles the interim correctly.
- **Version not bumped this session:** All changes are code quality / compliance — no user-visible behaviour change. Bump to 2.9.0 before next packaging.

### What's next

- Bump version to 2.9.0 before next zip is packaged
- Rename slug before WP.org submission (`admin-menu-manager` is taken on WP.org)
- Submenu ordering, active state highlighting, per-role configuration (unchanged from prior sessions)
