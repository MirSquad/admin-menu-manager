---
title: "Admin Menu Manager — Session Opener"
doc_type: session-opener
project: admin-menu-manager
created: 2026-03-29
updated: 2026-04-03
status: active
summary: "Paste at the start of every session on admin-menu-manager. Doc manifest and standing update instructions."
tags: [wordpress, plugin, admin, sidebar, menu]
blog_candidate: false
---

# Admin Menu Manager — Session Opener

Paste this at the start of every working session on this project. It is not the handoff doc — it is the doc management layer. It tells you what docs exist and instructs you to produce updated content at session end, automatically.

**To end a session and trigger all doc updates: say "wrap up."**

---

## Project snapshot

Single-file WordPress plugin that lets admins reorder, hide, group, and nest items in the wp-admin sidebar. Currently at v2.8.0, delivered as an installable zip. Personal/workflow tool, not for public distribution.

---

## Doc manifest

| Doc | Filename | Update trigger |
|---|---|---|
| Session Opener | `admin-menu-manager-session-opener.md` | When open items or doc structure change |
| Handoff | `admin-menu-manager-handoff.md` | Every session — reflects current state |
| Project Context | `admin-menu-manager-project-context.md` | When architecture or technical details change |
| Changelog | `admin-menu-manager-changelog.md` | Every session — append a new entry |
| Decisions Log | `admin-menu-manager-decisions-log.md` | When a significant decision is made |

All files live in: `Claude Work/dev-work/plugins/admin-menu-manager/`

---

## Relevant skills

- `work-docs` — doc management, frontmatter, five-pillar system
- `plugin-builder` — WordPress plugin standards, version bump rules, packaging checklist, capability checks

---

## Critical context before touching anything

**WordPress only supports two menu levels natively** — do not attempt three-level nesting. When an item is moved into a custom group, its own sub-items get flattened into that group's submenu. This is a platform constraint, not a plugin limitation.

**Slug encoding in POST is load-bearing** — menu slugs like `edit.php?post_type=page` contain characters that break PHP POST key parsing. The current approach serializes everything as a single JSON payload (`mm_payload`) on form submit. If the form submission mechanism ever changes, this must be preserved or slug-based items will silently fail.

**Do NOT unset `$submenu[$cs]` after flattening** — WordPress's `user_can_access_admin_page()` walks all of `$submenu` to verify access. Removing entries causes "not allowed" errors on sub-pages even when the URL is correct. Orphaned entries are invisible in the UI (no top-level `$menu` parent pointing to them). See Decisions Log.

**`normalize_menu_url()` is load-bearing** — bare page slugs like `ai1wm_import` must be converted to `admin.php?page=ai1wm_import` before being stored in a flattened submenu. Without this, WordPress builds the URL relative to whatever the new parent `.php` file is, causing 404s. See Decisions Log.

Before changing the save/load or menu rebuild mechanism:
1. Read the Decisions Log entry on JSON payload serialization
2. Read the Decisions Log entry on `apply_config()` hook priority
3. Read the Decisions Log entry on `$submenu` retention
4. Read the Decisions Log entry on `normalize_menu_url`

---

## Open items

- Submenu ordering: items nested under a parent always append after the parent's original sub-items — no control over relative order yet
- Active state highlighting for custom groups in the sidebar (WP limitation with custom top-level items not backed by a real page)
- Per-role configuration — currently applies to all `manage_options` users

---

## Version bump checklist

Run every session that produces a new zip. Per `plugin-builder` skill — never deliver a zip at the same version as the previous one.

1. Update `Version:` in the plugin header (`admin-menu-manager.php`, line 6)
2. No version constant in this plugin currently — add to checklist if one is introduced
3. Update version in **Handoff doc** current state section
4. Update version in **Session opener** project snapshot (this file)
5. Package: `cd /home/claude && zip -r admin-menu-manager.zip admin-menu-manager/`
6. Copy to outputs: `cp admin-menu-manager.zip /mnt/user-data/outputs/admin-menu-manager.zip`

---

## Standing instructions for Claude

When Miriam says "wrap up," produce the following without being asked. Always output complete files — never snippets, partial content, or diff-style changes. Every doc that changed must be output in full so Miriam can do a straight replacement.

**Source docs:** Always base wrap-up output on the files Miriam attached at the start of the session. Do not use project files at `/mnt/project/` as the source — those are read-only copies that may be one or more sessions behind the attached files.

1. **Changelog:** Output the complete updated `admin-menu-manager-changelog.md` with a new entry appended — date, what changed at the file level (specific, not vague), decisions made, dead ends, what's next.

2. **Handoff doc:** If current state changed (version, status, open items, fragile things), output the complete updated `admin-menu-manager-handoff.md`.

3. **Decisions log:** If a new architectural decision was made, output the complete updated `admin-menu-manager-decisions-log.md` with the new entry appended — problem, decision, why, risks, conditions that could change it.

4. **Project context:** If file structure, architecture, dependencies, or any technical detail changed, output the complete updated `admin-menu-manager-project-context.md`.

5. **plugin-builder skill:** If any WordPress or plugin-specific insight was discovered (gotcha, pattern, workaround), output the complete updated `plugin-builder-SKILL.md` in full and package it as a `.skill` file for installation. Never output a snippet — always the complete file plus the packaged skill.

6. **Version bump:** If a new plugin zip was produced this session, confirm the version number was incremented in every location in the checklist above. If it was not bumped, flag it and output the corrected version strings.

7. **Open items:** Output an updated version of the open items section above.

8. **Frontmatter:** Output updated frontmatter blocks for every doc that changed, with today's actual date in the `updated` field.

9. **Session opener:** If anything changed, output the complete updated version of this file.
