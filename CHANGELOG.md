# Changelog

## 2.9.4 — 2026-08-10

- Changed: Removed the "Enable write abilities" settings checkbox for the Abilities API. The reset-config ability (already marked destructive) is now always registered — confirmation happens via the AI client, not a site-wide toggle.

## 2.9.3 — 2026-08-05

- Hardening: admin redirects now use `wp_safe_redirect()`, and page output is explicitly escaped. WordPress coding-standards cleanup. No changes to behavior.

## 2.8.0 — 2026-05-21

- Improved: Full internationalization — all admin strings wrapped for translation.
- Improved: Plugin header now includes all required fields.
- Added: `uninstall.php` to clean up saved settings on deletion.
- Fixed: Missing text domain on Settings action link.

## 2.7.0

- Added: Nesting support — move any top-level menu item under another item.
- Fixed: Admin access check when accessing reparented submenu pages.

## 2.6.0

- Added: Custom groups — create named groups and assign menu items to them.

## 1.0.0

- Initial release.
