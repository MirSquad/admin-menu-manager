---
title: "Admin Menu Manager — Handoff Doc"
doc_type: handoff
project: admin-menu-manager
created: 2026-03-29
updated: 2026-05-25
status: active
summary: "Current state snapshot for the Admin Menu Manager plugin. Read this before any session."
tags: [wordpress, plugin, admin, sidebar, menu]
blog_candidate: false
---

# Admin Menu Manager — Handoff Doc

Read this first, every session.

---

## Current state

**Version:** 2.8.0 (bump to 2.9.0 before next packaging)
**File:** `admin-menu-manager/admin-menu-manager.php` (single-file plugin)
**Deliverable:** Installable `.zip` — user uploads via Plugins > Add New > Upload Plugin
**Settings page:** Settings > Admin Menu Manager

**New files added in Session 4:**
- `uninstall.php` — deletes `wpa_mm_config` on plugin deletion
- `readme.txt` — WP.org-format readme
- `languages/` directory — placeholder for translations

**WP.org status:** Plugin header, i18n, escaping, and readme are now WP.org-ready. **Exception: the slug `admin-menu-manager` is taken on WP.org.** Must be renamed (folder, text domain, option keys) before submitting. A `plugin_row_meta` filter removes the broken "View details" link in the interim.

**What's working:**
- Drag-and-drop reordering via jQuery UI Sortable (bundled in WP admin — no extra dependencies)
- Custom groups as inline rows in the unified list — draggable and orderable alongside other items, displayed with blue background and GROUP badge
- Hide checkbox per item — removes from sidebar entirely
- Parent dropdown: nest under a custom group, or nest under another top-level item (↳)
- When items are nested, their sub-items are preserved and flattened inside the parent
- Group rename and delete — reflected live in all parent dropdowns without page reload
- Settings link in the Plugins list page (via `plugin_action_links_` filter)
- Reset to defaults button — clears all config and returns sidebar to WP defaults
- Consistent naming: "Admin Menu Manager" everywhere
- All inline JS strings translatable via `wpaMM` PHP-generated object

**What's not yet built:**
- Control over relative order of nested items within a parent's existing submenu (added items always append after original sub-items)
- Active state highlighting for custom groups in the sidebar
- Per-role configuration (currently applies to all `manage_options` users)

---

## Most important constraint

WordPress admin menus natively support only two levels. When an item is nested inside a custom group, its own sub-items get flattened into the group's submenu directly below it. This is a platform constraint — do not attempt three-level nesting.

---

## What's fragile

**JSON payload serialization** — All form data is serialized into a single `mm_payload` JSON string on submit, bypassing PHP POST key parsing issues with slugs containing `?`, `=`, and `&`. If the form submission mechanism changes, this must be preserved. See Decisions Log.

**`apply_config()` hook priority** — Runs at `admin_menu` priority 999 so all plugins have registered their menus before the snapshot is taken. Lowering this causes late-registering plugins to disappear. See Decisions Log.

**`$orig_snap` class property** — `apply_config()` stores a snapshot of the original `$menu` before modifying it. `render_page()` reads from this snapshot, not from the modified `$menu`. If this property isn't populated (e.g. no config saved yet), `render_page()` falls back to reading `$menu` directly. Do not remove this property or the settings page will show items incorrectly.

**`$submenu[$cs]` must NOT be unset** — After flattening a plugin's sub-items into a new parent, the original `$submenu` entry must be left in place. WordPress's `user_can_access_admin_page()` searches all of `$submenu` to validate access — removing entries causes "not allowed" errors on sub-pages. See Decisions Log.

**`normalize_menu_url()`** — All menu item URLs and their sub-items must be passed through this function when flattening. Bare slugs like `ai1wm_import` get converted to `admin.php?page=ai1wm_import`. Without this, WordPress builds URLs relative to the new parent `.php` file, causing 404s. See Decisions Log.

**Group submenu key** — The group's `$menu` entry uses `$first_url` (the first child's URL) as its slug. WordPress looks for `$submenu[$menu[n][2]]` when rendering — so the submenu must be stored under `$first_url`, not under `$gslug`. This is non-obvious but critical.

---

## Tried and ruled out

- **Number fields for ordering (v1):** Replaced with drag-and-drop in v2.
- **Groups as a separate section above the item list (v1):** Groups must be inline.
- **URL-safe base64 per-key encoding (v1):** Replaced with JSON payload.
- **`unset($submenu[$cs])` after flattening:** Causes "not allowed" access errors. Left orphaned intentionally.

---

## Open items

- Submenu ordering: nested items always append after the parent's original sub-items — no relative order control
- Active state highlighting for custom groups
- Per-role configuration

---

## Document index

| Doc | What it answers |
|---|---|
| `admin-menu-manager-session-opener.md` | Doc manifest, open items, standing instructions |
| This doc | What to know right now |
| `admin-menu-manager-project-context.md` | Full technical architecture |
| `admin-menu-manager-changelog.md` | Full history of what changed and why |
| `admin-menu-manager-decisions-log.md` | Why it's built this way |
