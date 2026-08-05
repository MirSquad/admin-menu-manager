# Admin Menu Manager WordPress Plugin

A WordPress plugin for managing the wp-admin sidebar menu. Drag-and-drop reordering, hide items you don't use, group related items under custom headings, and nest top-level items as sub-items of other items.

## Why

The WordPress admin sidebar gets cluttered fast — every plugin adds its own menu entry, and the default ordering rarely matches how you actually work. This plugin lets you reshape the sidebar to fit your workflow without touching code.

## Features

- **Drag-and-drop reordering** of all top-level sidebar items
- **Hide items** you rarely use (they're still accessible, just not visible in the sidebar)
- **Custom groups** — create named groups with dashicon icons to organize related items under a single expandable heading
- **Nesting** — move any top-level item under another item as a sub-item
- **Reset to defaults** with one click
- **Settings link** in the Plugins list for quick access

## How it works

The plugin runs at `admin_menu` priority 999 — after all plugins have registered their menus — and rebuilds the sidebar from your saved configuration. Settings are stored in `wp_options` and apply to admin users only (`manage_options` capability).

Single PHP file, no build process, no external dependencies beyond jQuery UI Sortable (bundled with WordPress).

## Installation

1. Download or clone this repository
2. Copy the `admin-menu-manager` folder into `wp-content/plugins/`
3. Activate the plugin in WordPress
4. Go to **Settings > Admin Menu Manager** to configure

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## Requirements

- WordPress 5.0+
- PHP 7.4+

## License

GPL-2.0-or-later

## WordPress Abilities API

This plugin exposes abilities for the [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/) (WordPress 6.9+), making it manageable by AI agents via the [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin.

### Requirements

- WordPress 6.9+
- [MCP Adapter plugin](https://github.com/WordPress/mcp-adapter)

### Available abilities

| Ability | Access | Description |
|---|---|---|
| `admin-menu-manager/get-config` | Always on | Returns the full saved menu configuration: item order, hidden flags, custom groups, and parent assignments |
| `admin-menu-manager/reset-config` | Write (opt-in) | Resets the admin sidebar to WordPress defaults by deleting all saved configuration |

### Enabling write abilities

Write abilities are disabled by default. To enable them, go to **Settings > Admin Menu Manager** and check **Enable write abilities** under the Abilities API section.

> **Note:** `reset-config` is destructive and cannot be undone. Only enable write abilities if you trust the AI agent that has access to your site.
