=== Admin Menu Manager ===
Contributors: miriamschwab
Tags: admin menu, sidebar, menu reorder, admin ui, drag and drop
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.9.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop reordering, hiding, grouping, and nesting of WordPress admin sidebar items.

== Description ==

Admin Menu Manager lets you reorganize the WordPress admin sidebar to match the way you actually work. Drag items into any order, hide ones you never use, group related items under a custom heading, or nest plugins under a single parent.

**Features**

* Drag and drop to reorder any admin menu item
* Hide items you don't need — they remain accessible via direct URL
* Create custom groups to collect related items under a single heading
* Nest a plugin's top-level menu under another item
* All changes apply only to admin users
* One-click reset to restore WordPress defaults

**Notes**

Settings apply to users with the `manage_options` capability (administrators). Other roles see the default WordPress menu.

== Installation ==

1. Upload the `admin-menu-manager` folder to `/wp-content/plugins/`.
2. Activate from **Plugins > Installed Plugins**.
3. Go to **Settings > Admin Menu Manager** and drag items to your preferred order.

== Frequently Asked Questions ==

= Will hiding an item break anything? =

No. Hiding removes the item from the sidebar but does not affect functionality. You can still reach any hidden page via its direct URL.

= Does this affect all users? =

No. Changes apply only to administrators (users with `manage_options` capability).

= How do I undo all changes? =

Use the **Reset to WordPress Defaults** button at the bottom of the settings page. This clears all saved configuration.

== Changelog ==

= 2.9.3 - 2026-08-05 =
* Hardening: admin redirects now use wp_safe_redirect(), and page output is explicitly escaped. WordPress coding-standards cleanup. No changes to behavior.

= 2.8.0 - 2026-05-21 =
* Improved: Full internationalization — all admin strings wrapped for translation.
* Improved: Plugin header now includes all required fields.
* Added: uninstall.php to clean up saved settings on deletion.
* Fixed: Missing text domain on Settings action link.

= 2.7.0 =
* Added: Nesting support — move any top-level menu item under another item.
* Fixed: Admin access check when accessing reparented submenu pages.

= 2.6.0 =
* Added: Custom groups — create named groups and assign menu items to them.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.8.0 =
Adds full i18n, complete plugin header, and uninstall cleanup. Recommended update.
