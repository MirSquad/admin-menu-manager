---
title: "Admin Menu Manager — Project Context"
doc_type: project-context
project: admin-menu-manager
created: 2026-03-29
updated: 2026-03-29
status: active
summary: "Full technical reference for the Admin Menu Manager plugin — architecture, how components connect, data flow, and non-obvious behaviours."
tags: [wordpress, plugin, admin, sidebar, menu, php, jquery]
blog_candidate: false
---

# Admin Menu Manager — Project Context

---

## What this project is

A personal WordPress plugin for managing the wp-admin sidebar. Admins can reorder top-level items, hide rarely-used ones, group items under a custom top-level entry, and nest top-level items as sub-items of other top-level items.

Settings apply to `manage_options` users (admins) only. Not intended for public distribution.

---

## File structure

```
admin-menu-manager/
└── admin-menu-manager.php    Single-file plugin — all PHP, HTML, CSS, and JS
```

No build process. No external dependencies beyond jQuery UI Sortable, which is bundled in the WordPress admin.

---

## Class structure

Everything lives in the `WPA_Menu_Manager` class, instantiated inside `if ( is_admin() )` at the bottom of the file.

**Constants:**
- `OPTION` — `wpa_mm_config` — the `wp_options` key where config is stored (autoload: false)
- `SLUG` — `wpa-menu-manager` — the settings page slug
- `ACTION` — `wpa_mm_save` — the `admin-post.php` action name

**Properties:**
- `$orig_snap` — stores a snapshot of the original `$menu` before `apply_config()` modifies it; used by `render_page()` so all items always appear in the settings list
- `$config` — caches the result of `get_option()` to avoid multiple DB calls per request

**Public methods (hooked):**

| Method | Hook | Priority | What it does |
|---|---|---|---|
| `register_page()` | `admin_menu` | default | Registers Settings > Admin Menu Manager |
| `apply_config()` | `admin_menu` | 999 | Rebuilds `$menu` and `$submenu` from saved config |
| `handle_save()` | `admin_post_wpa_mm_save` | default | Processes form submission and reset |
| `enqueue()` | `admin_enqueue_scripts` | default | Enqueues jQuery UI Sortable on settings page only |
| `show_notice()` | `admin_notices` | default | Displays save/reset confirmation notices |
| `action_links()` | `plugin_action_links_` filter | default | Adds Settings link in the Plugins list page |

---

## Data model

Config is stored as a serialized PHP array in `wp_options` under `wpa_mm_config` with `autoload = false`.

```php
[
  'groups' => [
    [
      'id'    => 'g1234567890',   // client-generated Date.now() ID
      'title' => 'My Group',
      'icon'  => 'dashicons-category',
      'order' => 3,               // position in the list at save time
    ],
  ],
  'items' => [
    [
      'slug'   => 'index.php',    // WP menu slug ($menu[n][2])
      'hidden' => false,
      'order'  => 1,              // position in the list at save time
      'parent' => null,           // null = top level
                                  // 'group:g1234567890' = inside a group
                                  // 'item:tools.php' = nested under another item
    ],
  ],
]
```

---

## How `apply_config()` works

Runs at `admin_menu` priority 999 — after all plugins have registered their menus.

**Step 1 — Snapshot:** Loops `$menu`, builds `$snap` keyed by slug with item array, original position, and sub-item count. Skips separators. Stores in `$this->orig_snap` before any modifications.

**Step 2 — Pre-index:** Builds two lookup structures from config:
- `$groups_by_id` — group data by ID
- `$by_parent` — items indexed by their parent key (e.g. `'group:g123'`, `'item:tools.php'`)
- `$configured_set` — set of all configured slugs for O(1) "is this item in config?" checks

**Step 3 — Build top-level list:** Collects three categories into `$top[]`:
- Items with no parent (visible, not assigned to a group or item)
- Groups with at least one visible child (using `$by_parent` for O(1) lookup)
- Items not in config at all — appended at `900 + original_position`

Sorts by `order`.

**Step 4 — Rebuild `$menu`:** Iterates sorted `$top`, writes to `$menu` starting at position 2, incrementing by 2.

- **Item rows:** Written directly. If other items are parented to this slug, they're absorbed: their entries are appended to this item's `$submenu`, with all URLs passed through `normalize_menu_url()`. Original `$submenu[$cs]` entries are NOT unset (see Decisions Log #7).
- **Group rows:** A synthetic `$menu` entry is created. All group children are flattened into a `$submenu` keyed by the first child's URL (`$first_url`). The group's `$menu` entry URL is also `$first_url` — these must match for WP to render the submenu (see Decisions Log #6).

---

## How the settings page works

**Rendering:** `render_page()` reads from `$this->orig_snap` (set by `apply_config()`), not from the current `$menu`. This ensures items moved into groups or nested under other items still appear in the settings list. Falls back to reading `$menu` directly if no config has been saved yet.

**Drag-and-drop:** jQuery UI Sortable on `#mm-list`. Handle class `.mm-handle`. All `<li>` rows are sortable regardless of type.

**Group rows:** Rendered with `data-type="group"` and `data-id`. Add/rename/delete operations update the DOM and all `<select>` dropdowns live.

**Parent dropdown:** Each item has a `<select class="mm-parent-select">` with two optgroups:
- "Groups" — one option per custom group
- "Nest under item" — one option per top-level item (excluding self)

**Payload:** On submit, `buildPayload()` walks `#mm-list > li` in DOM order, builds a JSON array, writes to `#mm-payload`. Order is determined by DOM position — there are no explicit order fields.

---

## Private utility methods

**`normalize_menu_url( $url )`** — Converts bare page slugs (e.g. `ai1wm_import`) to `admin.php?page=ai1wm_import`. Passes through URLs already containing `.php` or `://`. Applied to all item and sub-item URLs when flattening. See Decisions Log #8.

**`clean_title( $title )`** — Strips HTML tags from menu titles (removes notification bubbles like `<span>5</span>`).

**`get_config()`** — Returns config array, cached in `$this->config` after first call.

**`save_config( $c )`** — Saves config with `autoload = false`.

---

## WordPress-specific constraints

- **Two menu levels only.** No third tier exists in WP's menu rendering pipeline.
- **Custom group active state.** WP doesn't auto-highlight a custom top-level item when on a child page, because the group isn't backed by a real page.
- **Menu separators.** Skipped in the snapshot loop and not configurable.
- **Capability check.** All hooks check `current_user_can('manage_options')` before acting.
- **`$submenu` access validation.** WordPress validates page access by searching all of `$submenu`. Never remove entries from `$submenu` after flattening — doing so causes "not allowed" errors.
