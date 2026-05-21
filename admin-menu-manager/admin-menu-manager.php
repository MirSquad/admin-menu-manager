<?php
/**
 * Plugin Name: Admin Menu Manager
 * Description: Drag-and-drop reordering, hiding, grouping, and nesting of admin sidebar items. Applies to admin users only.
 * Version:     2.8.0
 * Author:      Custom
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPA_Menu_Manager {

	private $orig_snap = [];
	private $config    = null;

	const OPTION = 'wpa_mm_config';
	const SLUG   = 'wpa-menu-manager';
	const ACTION = 'wpa_mm_save';

	public function __construct() {
		add_action( 'admin_menu',                 [ $this, 'register_page' ]       );
		add_action( 'admin_menu',                 [ $this, 'apply_config'  ], 999  );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_save'   ]       );
		add_action( 'admin_enqueue_scripts',      [ $this, 'enqueue'       ]       );
		add_action( 'admin_notices',              [ $this, 'show_notice'   ]       );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'action_links' ] );
	}

	public function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
			__( 'Settings' )
		);
		array_unshift( $links, $settings );
		return $links;
	}

	// ── Config ────────────────────────────────────────────────────────────────

	private function get_config(): array {
		if ( $this->config === null ) {
			$this->config = array_merge(
				[ 'groups' => [], 'items' => [] ],
				(array) get_option( self::OPTION, [] )
			);
		}
		return $this->config;
	}

	private function save_config( array $c ): void {
		// autoload=false — config is only needed in admin, not on every frontend request.
		update_option( self::OPTION, $c, false );
	}

	// ── Hooks ─────────────────────────────────────────────────────────────────

	public function register_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		add_options_page(
			'Admin Menu Manager', 'Admin Menu Manager',
			'manage_options', self::SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function enqueue( $hook ): void {
		if ( strpos( $hook, 'menu-manager' ) === false ) return;
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	// ── Apply saved config to live menu ───────────────────────────────────────
	//
	// Runs at priority 999 so all plugins have registered.
	// Snapshots $menu, wipes it, rebuilds from config.
	// Parents can be: a custom group ("group:ID") or another item ("item:slug").

	public function apply_config(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		global $menu, $submenu;
		$config = $this->get_config();
		if ( empty( $config['items'] ) && empty( $config['groups'] ) ) return;

		// Snapshot: slug => [item array, original position, sub-item count]
		// Stored as class property so render_page() sees the original menu regardless of
		// what apply_config() removes from $menu.
		$snap = [];
		foreach ( $menu as $pos => $item ) {
			if ( empty( $item[2] ) ) continue;
			if ( strpos( $item[4] ?? '', 'separator' ) !== false ) continue;
			$snap[ $item[2] ] = [
				'item'  => $item,
				'pos'   => (int) $pos,
				'n_sub' => isset( $submenu[ $item[2] ] ) ? count( $submenu[ $item[2] ] ) : 0,
			];
		}
		$this->orig_snap = $snap;

		// Index groups by ID
		$groups_by_id = [];
		foreach ( $config['groups'] as $g ) {
			$groups_by_id[ $g['id'] ] = $g;
		}

		// ── Pre-index items by parent (avoids O(n²) child-finding loops below) ──

		$by_parent      = []; // parent_key => [ item_config, ... ]
		$configured_set = []; // slug => true  (O(1) lookup for unconfigured items)

		foreach ( $config['items'] as $ic ) {
			$configured_set[ $ic['slug'] ] = true;
			$p = $ic['parent'] ?? '';
			if ( $p ) {
				$by_parent[ $p ][] = $ic;
			}
		}

		// ── Build ordered top-level list ──

		$top = [];

		// Items with no parent
		foreach ( $config['items'] as $ic ) {
			if ( ! empty( $ic['parent'] ) || ! empty( $ic['hidden'] ) ) continue;
			if ( ! isset( $snap[ $ic['slug'] ] ) ) continue;
			$top[] = [ 'type' => 'item', 'order' => (int) $ic['order'], 'slug' => $ic['slug'] ];
		}

		// Groups — only include if they have at least one visible child
		foreach ( $config['groups'] as $g ) {
			$key      = 'group:' . $g['id'];
			$children = isset( $by_parent[ $key ] ) ? $by_parent[ $key ] : [];
			$has      = false;
			foreach ( $children as $ic ) {
				if ( empty( $ic['hidden'] ) && isset( $snap[ $ic['slug'] ] ) ) {
					$has = true;
					break;
				}
			}
			if ( ! $has ) continue;
			$top[] = [ 'type' => 'group', 'order' => (int) ( $g['order'] ?? 500 ), 'id' => $g['id'] ];
		}

		// Items not yet in config: append at original position
		foreach ( $snap as $slug => $info ) {
			if ( isset( $configured_set[ $slug ] ) ) continue;
			$top[] = [ 'type' => 'item', 'order' => 900 + $info['pos'], 'slug' => $slug ];
		}

		usort( $top, function( $a, $b ) { return $a['order'] - $b['order']; } );

		// ── Rebuild $menu ──

		$menu = [];
		$pos  = 2;

		foreach ( $top as $te ) {

			if ( $te['type'] === 'item' ) {
				$slug         = $te['slug'];
				$menu[ $pos ] = $snap[ $slug ]['item'];

				// Absorb any items parented to this slug (pre-indexed above)
				$raw_children = isset( $by_parent[ 'item:' . $slug ] ) ? $by_parent[ 'item:' . $slug ] : [];
				$children = [];
				foreach ( $raw_children as $ic ) {
					if ( ! empty( $ic['hidden'] ) || ! isset( $snap[ $ic['slug'] ] ) ) continue;
					$children[] = $ic;
				}
				if ( ! empty( $children ) ) {
					usort( $children, function( $a, $b ) { return (int) $a['order'] - (int) $b['order']; } );
					$subs = isset( $submenu[ $slug ] ) ? $submenu[ $slug ] : [];
					foreach ( $children as $child ) {
						$cs     = $child['slug'];
						$co     = $snap[ $cs ]['item'];
						$subs[] = [
							$this->clean_title( $co[0] ),
							$co[1],
							$this->normalize_menu_url( $co[2] ),
							$co[3] ?? $this->clean_title( $co[0] ),
						];
						if ( isset( $submenu[ $cs ] ) ) {
						foreach ( $submenu[ $cs ] as $s ) {
							$s[2] = $this->normalize_menu_url( $s[2] );
							$subs[] = $s;
						}
						// Do NOT unset $submenu[$cs] — WP's user_can_access_admin_page() searches
						// all of $submenu to verify access. Removing it causes "not allowed" errors.
						// Orphaned entries are invisible in the UI (no $menu top-level parent).
						}
					}
					$submenu[ $slug ] = $subs;
				}

			} elseif ( $te['type'] === 'group' ) {
				$g     = $groups_by_id[ $te['id'] ];
				$gslug = 'wpa_mm_grp_' . $g['id'];

				// Children pre-indexed above
				$raw_children = $by_parent[ 'group:' . $g['id'] ] ?? [];
				$children = [];
				foreach ( $raw_children as $ic ) {
					if ( ! empty( $ic['hidden'] ) || ! isset( $snap[ $ic['slug'] ] ) ) continue;
					$children[] = $ic;
				}
				usort( $children, function( $a, $b ) { return (int) $a['order'] - (int) $b['order']; } );

				$subs = [];
				foreach ( $children as $child ) {
					$cs     = $child['slug'];
					$co     = $snap[ $cs ]['item'];
				$subs[] = [
					$this->clean_title( $co[0] ),
					$co[1],
					$this->normalize_menu_url( $co[2] ),
					$co[3] ?? $this->clean_title( $co[0] ),
				];
					if ( isset( $submenu[ $cs ] ) ) {
						foreach ( $submenu[ $cs ] as $s ) {
							$s[2] = $this->normalize_menu_url( $s[2] );
							$subs[] = $s;
						}
						// Do NOT unset $submenu[$cs] — same reason as above.
					}
				}

				$first_url    = $subs[0][2] ?? '#';
				$menu[ $pos ] = [
					$g['title'], 'manage_options', $first_url, $g['title'],
					'menu-top menu-icon-generic',
					'toplevel_page_' . $gslug,
					$g['icon'] ?? 'dashicons-category',
				];
				// Store submenu under $first_url so WordPress can find it when rendering.
				// WP looks for $submenu[$menu[n][2]] — if we use $gslug here instead,
				// the submenu never renders.
				if ( $first_url !== '#' ) {
					$submenu[ $first_url ] = $subs;
				}
			}

			$pos += 2;
		}
	}

	// ── Save handler ──────────────────────────────────────────────────────────

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
		check_admin_referer( 'wpa_mm_nonce' );

		// Reset: wipe all saved config and return to WP defaults
		if ( ! empty( $_POST['wpa_reset'] ) ) {
			delete_option( self::OPTION );
			wp_redirect( add_query_arg(
				[ 'page' => self::SLUG, 'reset' => 1 ],
				admin_url( 'options-general.php' )
			) );
			exit;
		}

		$raw = isset( $_POST['mm_payload'] ) ? wp_unslash( $_POST['mm_payload'] ) : '{}';
		// Reject payloads over 200 KB — no legitimate menu config comes close to this.
		if ( strlen( $raw ) > 204800 ) {
			wp_die( 'Payload too large.', 400 );
		}
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || empty( $data['list'] ) ) {
			wp_redirect( add_query_arg(
				[ 'page' => self::SLUG, 'updated' => 1 ],
				admin_url( 'options-general.php' )
			) );
			exit;
		}

		$new_items  = [];
		$new_groups = [];
		$order      = 1;

		foreach ( $data['list'] as $entry ) {
			$type = sanitize_text_field( $entry['type'] ?? '' );

			if ( $type === 'item' ) {
				$slug = mb_substr( sanitize_text_field( $entry['slug'] ?? '' ), 0, 500 );
				if ( ! $slug ) { $order++; continue; }
				$new_items[] = [
					'slug'   => $slug,
					'hidden' => ! empty( $entry['hidden'] ),
					'order'  => $order,
					'parent' => sanitize_text_field( $entry['parent'] ?? '' ) ?: null,
				];

			} elseif ( $type === 'group' ) {
				$gid   = sanitize_text_field( $entry['id'] ?? '' );
				$title = mb_substr( sanitize_text_field( $entry['title'] ?? '' ), 0, 200 );
				if ( $gid && $title ) {
					$new_groups[] = [
						'id'    => $gid,
						'title' => $title,
						'icon'  => 'dashicons-category',
						'order' => $order,
					];
				}
			}

			$order++;
		}

		$this->save_config( [ 'items' => $new_items, 'groups' => $new_groups ] );

		wp_redirect( add_query_arg(
			[ 'page' => self::SLUG, 'updated' => 1 ],
			admin_url( 'options-general.php' )
		) );
		exit;
	}

	// ── Admin notice ──────────────────────────────────────────────────────────

	public function show_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'menu-manager' ) === false ) return;
		if ( ! empty( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Menu settings saved.</p></div>';
		}
		if ( ! empty( $_GET['reset'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>Menu reset to WordPress defaults. All custom settings have been cleared.</p></div>';
		}
	}

	// ── Settings page ─────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		global $menu, $submenu;
		$config = $this->get_config();

		// Use the pre-modification snapshot stored by apply_config() so that items
		// which were moved into groups or nested under other items still appear in
		// the settings list. If apply_config() didn't run (no config yet), fall back
		// to reading the current $menu directly.
		$snap_items = [];
		$source = ! empty( $this->orig_snap ) ? $this->orig_snap : [];

		if ( empty( $source ) ) {
			foreach ( $menu as $pos => $item ) {
				if ( empty( $item[2] ) ) continue;
				if ( strpos( $item[5] ?? '', 'toplevel_page_wpa_mm_grp_' ) === 0 ) continue;
				if ( strpos( $item[4] ?? '', 'separator' ) !== false ) continue;
				$source[ $item[2] ] = [
					'item'  => $item,
					'pos'   => (int) $pos,
					'n_sub' => isset( $submenu[ $item[2] ] ) ? count( $submenu[ $item[2] ] ) : 0,
				];
			}
		}

		foreach ( $source as $slug => $info ) {
			$snap_items[ $slug ] = [
				'title' => $this->clean_title( $info['item'][0] ),
				'slug'  => $slug,
				'pos'   => $info['pos'],
				'n_sub' => $info['n_sub'],
			];
		}

		// Index saved config by slug
		$item_cfg = [];
		foreach ( $config['items'] as $ic ) {
			$item_cfg[ $ic['slug'] ] = $ic;
		}

		// Build combined display list (items + groups), sorted by saved order
		$entries = [];

		foreach ( $snap_items as $slug => $info ) {
			$ic        = isset( $item_cfg[ $slug ] ) ? $item_cfg[ $slug ] : null;
			$entries[] = [
				'type'   => 'item',
				'slug'   => $slug,
				'title'  => $info['title'],
				'n_sub'  => $info['n_sub'],
				'hidden' => $ic ? (bool) $ic['hidden'] : false,
				'order'  => $ic ? (int) $ic['order'] : 900 + $info['pos'],
				'parent' => $ic ? ( $ic['parent'] ?? '' ) : '',
			];
		}

		foreach ( $config['groups'] as $g ) {
			$entries[] = [
				'type'  => 'group',
				'id'    => $g['id'],
				'title' => $g['title'],
				'order' => (int) ( $g['order'] ?? 500 ),
			];
		}

		usort( $entries, function( $a, $b ) { return $a['order'] - $b['order']; } );

		// Parent dropdown options
		$group_opts = [];
		foreach ( $config['groups'] as $g ) {
			$group_opts[ 'group:' . $g['id'] ] = $g['title'];
		}
		$item_parent_opts = [];
		foreach ( $snap_items as $slug => $info ) {
			$item_parent_opts[ 'item:' . $slug ] = $info['title'];
		}

		?>
<div class="wrap">
<h1>Admin Menu Manager</h1>
<p style="max-width:680px;color:#555;">
	Drag rows to reorder. Use <strong>Parent</strong> to nest an item inside a group or another menu item —
	it disappears from the top level and its sub-items are preserved inside the parent.
	<strong>Hide</strong> removes the item entirely. Settings apply to admin users only.
</p>

<div style="background:#fff8e5;border-left:4px solid #f0b429;padding:10px 14px;margin:0 0 24px;font-size:13px;max-width:680px;">
	<strong>⚠ Don't hide Settings</strong> — that's where this page lives. Direct URL if you ever need it:<br>
	<code style="word-break:break-all;font-size:11px;"><?php echo esc_html( admin_url( 'options-general.php?page=' . self::SLUG ) ); ?></code>
</div>

<!-- Add group -->
<div style="display:flex;gap:8px;align-items:center;margin-bottom:20px;">
	<input type="text" id="new-group-title" placeholder="New group name" style="width:200px;">
	<button type="button" id="btn-add-group" class="button button-secondary">+ Add Group</button>
	<span style="color:#999;font-size:12px;">Groups appear inline — drag them into position like any other row.</span>
</div>

<form id="mm-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
<?php wp_nonce_field( 'wpa_mm_nonce' ); ?>
<input type="hidden" id="mm-payload" name="mm_payload" value="">

<div style="max-width:700px;">
	<!-- Column headers -->
	<div style="display:flex;align-items:center;padding:6px 10px;background:#f6f7f7;border:1px solid #ddd;border-bottom:none;border-radius:4px 4px 0 0;font-size:12px;color:#666;font-weight:600;">
		<span style="width:28px;flex-shrink:0;"></span>
		<span style="flex:1;">Item</span>
		<span style="width:50px;text-align:center;flex-shrink:0;">Hide</span>
		<span style="width:190px;flex-shrink:0;margin-left:8px;">Parent</span>
	</div>

	<!-- Sortable list -->
	<ul id="mm-list" style="list-style:none;margin:0;padding:0;border:1px solid #ddd;border-radius:0 0 4px 4px;">
	<?php
	$total = count( $entries );
	foreach ( $entries as $i => $entry ) :
		$row_border = ( $i < $total - 1 ) ? 'border-bottom:1px solid #eee;' : '';

		if ( $entry['type'] === 'group' ) :
			$gid = esc_attr( $entry['id'] );
	?>
	<li class="mm-row mm-group-row"
		data-type="group"
		data-id="<?php echo $gid; ?>"
		style="display:flex;align-items:center;padding:8px 10px;<?php echo esc_attr( $row_border ); ?>background:#eef2ff;cursor:move;">
		<span class="mm-handle dashicons dashicons-menu" title="Drag to reorder"
			style="color:#7c8fcc;cursor:grab;font-size:18px;width:28px;flex-shrink:0;"></span>
		<span style="flex:1;display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
			<strong class="mm-group-display">📁 <span class="mm-group-title-text"><?php echo esc_html( $entry['title'] ); ?></span></strong>
			<span style="font-size:11px;background:#7c8fcc;color:#fff;padding:1px 6px;border-radius:3px;">GROUP</span>
			<input type="text" class="mm-group-title-input"
				value="<?php echo esc_attr( $entry['title'] ); ?>"
				style="display:none;width:150px;" placeholder="Group name">
			<button type="button" class="button button-small btn-edit-group">Rename</button>
			<button type="button" class="button button-small btn-confirm-rename" style="display:none;">OK</button>
			<button type="button" class="button button-small button-link-delete btn-delete-group">✕ Delete</button>
		</span>
		<span style="width:50px;flex-shrink:0;"></span>
		<span style="width:190px;flex-shrink:0;margin-left:8px;"></span>
	</li>
	<?php else :
		$hidden = $entry['hidden'];
		$parent = $entry['parent'];
	?>
	<li class="mm-row mm-item-row"
		data-type="item"
		data-slug="<?php echo esc_attr( $entry['slug'] ); ?>"
		style="display:flex;align-items:center;padding:7px 10px;<?php echo esc_attr( $row_border ); ?>background:<?php echo $hidden ? '#fafafa' : '#fff'; ?>;cursor:move;<?php echo $hidden ? 'opacity:.4;' : ''; ?>">
		<span class="mm-handle dashicons dashicons-menu" title="Drag to reorder"
			style="color:#ccc;cursor:grab;font-size:18px;width:28px;flex-shrink:0;"></span>
		<span style="flex:1;">
			<?php echo esc_html( $entry['title'] ); ?>
			<?php if ( $entry['n_sub'] > 0 ) : ?>
			<span style="color:#aaa;font-size:11px;"> (<?php echo (int) $entry['n_sub']; ?> sub-items)</span>
			<?php endif; ?>
		</span>
		<label style="width:50px;flex-shrink:0;text-align:center;cursor:pointer;font-size:13px;" title="Hide this item">
			<input type="checkbox" class="mm-hide-cb" <?php checked( $hidden ); ?>>
		</label>
		<span style="width:190px;flex-shrink:0;margin-left:8px;">
			<select class="mm-parent-select" style="width:100%;font-size:12px;">
				<option value="">— Top level —</option>
				<?php if ( ! empty( $group_opts ) ) : ?>
				<optgroup label="Groups">
					<?php foreach ( $group_opts as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $parent, $val ); ?>>
						📁 <?php echo esc_html( $label ); ?>
					</option>
					<?php endforeach; ?>
				</optgroup>
				<?php endif; ?>
				<optgroup label="Nest under item">
					<?php foreach ( $item_parent_opts as $val => $label ) :
						if ( $val === 'item:' . $entry['slug'] ) continue;
					?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $parent, $val ); ?>>
						↳ <?php echo esc_html( $label ); ?>
					</option>
					<?php endforeach; ?>
				</optgroup>
			</select>
		</span>
	</li>
	<?php endif; ?>
	<?php endforeach; ?>
	</ul>
</div>

<p style="margin-top:16px;">
	<button type="submit" id="btn-save" class="button button-primary button-large">Save Menu Settings</button>
</p>
</form>

<div style="max-width:700px;margin-top:32px;padding-top:24px;border-top:1px solid #ddd;">
	<h3 style="margin-top:0;">Reset to defaults</h3>
	<p style="color:#555;font-size:13px;">Clears all saved settings and returns the sidebar to exactly how WordPress shows it by default. Use this if something has gone wrong and you want to start fresh.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		onsubmit="return confirm('This will clear all your menu settings and reset the sidebar to WordPress defaults. Are you sure?')">
		<?php wp_nonce_field( 'wpa_mm_nonce' ); ?>
		<input type="hidden" name="action"    value="<?php echo esc_attr( self::ACTION ); ?>">
		<input type="hidden" name="wpa_reset" value="1">
		<button type="submit" class="button button-large" style="color:#b32d2e;border-color:#b32d2e;">
			Reset to WordPress Defaults
		</button>
	</form>
</div>
</div>

<style>
#mm-list li { transition: opacity .15s; }
#mm-list li:hover { filter: brightness(.98); }
.mm-ph {
	list-style: none !important;
	background: #f0f4ff !important;
	border: 2px dashed #7c8fcc !important;
	border-radius: 4px !important;
}
</style>

<script>
jQuery(function($) {

	// ── Sortable ──────────────────────────────────────────────────────────────
	$('#mm-list').sortable({
		items:                '> li',
		handle:               '.mm-handle',
		axis:                 'y',
		tolerance:            'pointer',
		placeholder:          'mm-ph',
		forcePlaceholderSize: true,
		start: function(e, ui) {
			ui.placeholder.height(ui.item.outerHeight());
		}
	});

	// ── Hide checkbox ─────────────────────────────────────────────────────────
	$(document).on('change', '.mm-hide-cb', function() {
		var $row = $(this).closest('li');
		var on   = this.checked;
		$row.css({ opacity: on ? '.4' : '1', background: on ? '#fafafa' : '#fff' });
	});

	// ── Rename group ──────────────────────────────────────────────────────────
	$(document).on('click', '.btn-edit-group', function() {
		var $row = $(this).closest('li');
		$row.find('.mm-group-display').hide();
		$row.find('.mm-group-title-input').show().focus().select();
		$(this).hide();
		$row.find('.btn-confirm-rename').show();
	});

	$(document).on('keydown', '.mm-group-title-input', function(e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			$(this).closest('li').find('.btn-confirm-rename').trigger('click');
		}
	});

	$(document).on('click', '.btn-confirm-rename', function() {
		var $row  = $(this).closest('li');
		var title = $row.find('.mm-group-title-input').val().trim();
		if (!title) return;
		var gid   = String($row.data('id'));
		$row.find('.mm-group-title-text').text(title);
		$row.find('.mm-group-display').show();
		$row.find('.mm-group-title-input').hide();
		$row.find('.btn-edit-group').show();
		$(this).hide();
		// Sync label in all parent selects
		$('#mm-list .mm-parent-select option[value="group:' + gid + '"]').text('📁 ' + title);
	});

	// ── Delete group ──────────────────────────────────────────────────────────
	$(document).on('click', '.btn-delete-group', function() {
		if (!confirm('Delete this group? Items assigned to it will return to the top level.')) return;
		var $row = $(this).closest('li');
		var gid  = String($row.data('id'));
		$('#mm-list .mm-parent-select').each(function() {
			if ($(this).val() === 'group:' + gid) $(this).val('');
			$(this).find('option[value="group:' + gid + '"]').remove();
		});
		pruneEmptyOptgroups();
		$row.remove();
	});

	// ── Add group ─────────────────────────────────────────────────────────────
	$('#btn-add-group').on('click', function() {
		var title = $('#new-group-title').val().trim();
		if (!title) { alert('Please enter a group name.'); return; }
		var gid = 'g' + Date.now();
		$('#mm-list').append(buildGroupRow(gid, title));
		addGroupToSelects(gid, title);
		$('#new-group-title').val('').focus();
	});

	function buildGroupRow(gid, title) {
		var safe = $('<span>').text(title).html();
		return $('<li>')
			.addClass('mm-row mm-group-row')
			.attr({'data-type': 'group', 'data-id': gid})
			.css({display:'flex', 'align-items':'center', padding:'8px 10px',
				  'border-top':'1px solid #eee', background:'#eef2ff', cursor:'move'})
			.html(
				'<span class="mm-handle dashicons dashicons-menu" title="Drag to reorder" style="color:#7c8fcc;cursor:grab;font-size:18px;width:28px;flex-shrink:0;"></span>' +
				'<span style="flex:1;display:flex;align-items:center;flex-wrap:wrap;gap:6px;">' +
					'<strong class="mm-group-display">📁 <span class="mm-group-title-text">' + safe + '</span></strong>' +
					'<span style="font-size:11px;background:#7c8fcc;color:#fff;padding:1px 6px;border-radius:3px;">GROUP</span>' +
					'<input type="text" class="mm-group-title-input" value="' + safe + '" style="display:none;width:150px;" placeholder="Group name">' +
					'<button type="button" class="button button-small btn-edit-group">Rename</button>' +
					'<button type="button" class="button button-small btn-confirm-rename" style="display:none;">OK</button>' +
					'<button type="button" class="button button-small button-link-delete btn-delete-group">✕ Delete</button>' +
				'</span>' +
				'<span style="width:50px;flex-shrink:0;"></span>' +
				'<span style="width:190px;flex-shrink:0;margin-left:8px;"></span>'
			);
	}

	function addGroupToSelects(gid, title) {
		$('#mm-list .mm-parent-select').each(function() {
			var $og  = $(this).find('optgroup[label="Groups"]');
			var $opt = $('<option>').val('group:' + gid).text('📁 ' + title);
			if ($og.length) {
				$og.append($opt);
			} else {
				var $nest   = $(this).find('optgroup[label="Nest under item"]');
				var $newOg  = $('<optgroup label="Groups">').append($opt);
				if ($nest.length) { $nest.before($newOg); }
				else              { $(this).append($newOg); }
			}
		});
	}

	function pruneEmptyOptgroups() {
		$('#mm-list .mm-parent-select optgroup').each(function() {
			if ($(this).children('option').length === 0) $(this).remove();
		});
	}

	// ── Build payload & submit ────────────────────────────────────────────────

	function buildPayload() {
		var list = [];
		$('#mm-list > li').each(function() {
			var $row = $(this);
			var type = $row.data('type');
			if (type === 'group') {
				var inputVal = $row.find('.mm-group-title-input').val().trim();
				var spanVal  = $row.find('.mm-group-title-text').text().trim();
				list.push({
					type:  'group',
					id:    String($row.data('id')),
					title: inputVal || spanVal
				});
			} else {
				list.push({
					type:   'item',
					slug:   String($row.data('slug')),
					hidden: $row.find('.mm-hide-cb').is(':checked'),
					parent: $row.find('.mm-parent-select').val() || ''
				});
			}
		});
		return JSON.stringify({list: list});
	}

	$('#mm-form').on('submit', function() {
		$('#mm-payload').val(buildPayload());
	});

});
</script>
		<?php
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	private function clean_title( $title ): string {
		return strip_tags( (string) $title );
	}

	/**
	 * Normalize a menu slug to a usable admin URL.
	 *
	 * When WordPress renders a submenu link it builds: admin_url($parent) . '?page=' . $slug
	 * If the parent changes (e.g. a plugin's sub-items moved under tools.php), bare page
	 * slugs like 'ai1wm_import' produce 'tools.php?page=ai1wm_import' instead of
	 * 'admin.php?page=ai1wm_import', causing a frontend 404.
	 * Converting bare slugs to absolute admin.php URLs fixes this regardless of parent.
	 */
	private function normalize_menu_url( string $url ): string {
		if ( strpos( $url, '.php' ) !== false || strpos( $url, '://' ) !== false ) {
			return $url; // already absolute or external
		}
		return 'admin.php?page=' . $url;
	}
}

// Only instantiate in the admin — no frontend work to do.
if ( is_admin() ) {
	new WPA_Menu_Manager();
}
