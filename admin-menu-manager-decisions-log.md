---
title: "Admin Menu Manager — Decisions Log"
doc_type: decisions-log
project: admin-menu-manager
created: 2026-03-29
updated: 2026-03-29
status: active
summary: "Architectural decisions for the Admin Menu Manager plugin — why it's built the way it is."
tags: [wordpress, plugin, admin, sidebar, menu]
blog_candidate: false
---

# Admin Menu Manager — Decisions Log

---

## Decision 1 — JSON payload for form submission

**Problem:** WordPress menu slugs can contain characters like `?`, `=`, and `&` (e.g. `edit.php?post_type=page`). When used as PHP POST keys, these characters get mangled by PHP's query string parser.

**Decision:** Serialize all form data as a single JSON string in one hidden input (`mm_payload`) on form submit.

**What was tried first:** URL-safe base64 per POST key (v1). Worked for simple data but became unwieldy with the richer v2 structure.

**Why JSON works:** The slug only appears inside the JSON value, never as a POST key. JSON decoding with `json_decode()` is trivial.

**Risk:** Malformed JSON causes a silent no-op save. If silent failures are reported, add JSON error detection and a user-facing notice.

**Do not revert to per-key encoding** without also updating `buildPayload()` and `handle_save()` together.

---

## Decision 2 — `apply_config()` at `admin_menu` priority 999

**Problem:** `apply_config()` snapshots and rebuilds `$menu`. If it runs before other plugins register, those items are missing from the snapshot.

**Decision:** Hook at priority 999 — after WP core (5–30) and virtually all plugins (default 10).

**Risk:** A plugin hooking at priority 1000+ would still be missed. Very uncommon. If a plugin's items are missing, check its `add_menu_page()` priority.

**Do not lower this priority** without auditing all other `admin_menu` hooks in the active plugin set.

---

## Decision 3 — Groups inline in the unified sortable list

**Problem (v1):** Groups in a separate section had no way to control their position relative to other top-level items.

**Decision (v2):** Render groups as `<li>` rows in the same `<ul>` as item rows. Position in the DOM determines order.

**Why this works:** jQuery UI Sortable treats all `<li>` elements equally. `buildPayload()` walks the list in DOM order and emits `type: 'group'` or `type: 'item'` objects.

**Trade-off:** Group rows and item rows have different column layouts — the Hide and Parent columns are left empty on group rows.

---

## Decision 4 — Two-level menu constraint (platform, not plugin)

**Problem:** Users may expect three-level nesting (group > item > sub-items).

**Decision:** Accept the WordPress two-level limit. Sub-items of nested items are flattened into the parent's submenu.

**Why:** WordPress's `$menu`/`$submenu` pipeline has no third tier. Any workaround would require reimplementing WP's admin menu rendering.

**This constraint must be communicated to users** before they configure groups.

---

## Decision 5 — `$orig_snap` class property for render_page

**Problem:** `render_page()` was reading from the already-modified `$menu` global. Items moved into groups were removed from `$menu` by `apply_config()`, so they disappeared from the settings list. Groups also appeared as phantom item rows because their synthetic `$menu` entry used `$first_url` as the slug, which the filter didn't catch.

**Decision:** Store a snapshot of the original `$menu` as `$this->orig_snap` at the start of `apply_config()`, before any modifications. `render_page()` reads from this property instead of the current `$menu`.

**Why this works:** The snapshot is taken at priority 999 after all plugins have registered, so it contains every real menu item. `render_page()` always sees the full original menu regardless of what `apply_config()` did to `$menu`.

**Fallback:** If `$orig_snap` is empty (no config has been saved yet, so `apply_config()` returned early), `render_page()` falls back to reading `$menu` directly with its own group-filtering logic.

**Do not remove `$orig_snap`** or the settings page will show wrong items after any config is saved.

---

## Decision 6 — Group submenu stored under `$first_url`, not `$gslug`

**Problem:** Custom group menu entries were created with `$gslug` (`wpa_mm_grp_{id}`) as their URL, and the group's submenu was stored under `$submenu[$gslug]`. The group's submenu never rendered.

**Root cause:** WordPress renders submenus by looking up `$submenu[$menu[n][2]]` — the submenu key must match the URL stored in the `$menu` entry's URL field (`$menu[n][2]`). We were storing the submenu under `$gslug` but the `$menu` entry's URL was `$first_url`.

**Decision:** Store the group submenu under `$first_url` (the URL of the group's first child). The `$gslug` is still used as the menu CSS ID field (`$menu[n][5]`) for styling purposes, but never as the submenu key.

**Condition that could change this:** If WordPress ever changes how it looks up submenus, this would need revisiting. Currently true across WP 5.x and 6.x.

---

## Decision 7 — Do NOT unset `$submenu[$cs]` after flattening

**Problem:** After flattening a plugin's sub-items into a new parent, the original `$submenu[$cs]` entries were being unset. This caused "Sorry, you are not allowed to access this page" errors when navigating to those sub-pages, even though the URLs were correct.

**Root cause:** WordPress's `user_can_access_admin_page()` (called via `do_action('admin_menu')` and on every admin page load) validates access by searching all of `$submenu` for the current page slug. If the entry is gone, access is denied regardless of the user's capabilities.

**Decision:** Never unset `$submenu[$cs]`. Leave the original entries in place after copying them into the new parent's submenu.

**Why orphaned entries don't cause double display:** WordPress only renders a submenu if there is a corresponding top-level `$menu` entry. Orphaned entries (no `$menu` parent) are invisible in the UI but still searchable by the access check.

**Do not remove these entries** in future refactoring. The explanatory comments in the code must stay.

---

## Decision 8 — `normalize_menu_url()` for all flattened URLs

**Problem:** When plugin sub-items (e.g. All-in-One WP Migration's Import, Backups) are flattened into a new parent, bare page slugs like `ai1wm_import` produce wrong URLs. WordPress builds submenu URLs as `{parent-file}?page={slug}`. Under the original `admin.php`-based parent, `ai1wm_import` became `admin.php?page=ai1wm_import`. Moved under Tools, it became `tools.php?page=ai1wm_import` — a 404.

**Decision:** Pass all item URLs and sub-item URLs through `normalize_menu_url()` when flattening. This converts bare slugs to `admin.php?page=SLUG` and passes through URLs that already contain `.php` or `://`.

**Why the check works:** Any URL already containing `.php` is already absolute (e.g. `edit.php?post_type=page`, `admin.php?page=something`). External URLs contain `://`. Only bare page slugs — which are always just alphanumeric strings with underscores — need conversion.

**Risk:** If a plugin ever registers a sub-item with a bare slug that intentionally maps to a non-`admin.php` handler, this would break it. In practice this doesn't happen — all `admin.php?page=` registrations use bare slugs or full `admin.php` URLs.

**Apply in both flatten paths** — item-under-item and group — for both the top-level item entry and all its sub-items.
