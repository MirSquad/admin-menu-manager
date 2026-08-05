<?php
/**
 * WordPress Abilities API integration for Admin Menu Manager.
 * Requires WP 6.9+ (Abilities API). Does nothing on older versions.
 *
 * Read abilities are always registered.
 * Write abilities are only registered when "Enable write abilities" is on
 * in Settings > Admin Menu Manager.
 *
 * @package Admin_Menu_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bail silently on WordPress versions that don't have the Abilities API.
if ( ! function_exists( 'wp_register_ability' ) ) {
	return;
}

// -------------------------------------------------------------------------
// Register category.
// -------------------------------------------------------------------------
add_action( 'wp_abilities_api_categories_init', 'wpa_mm_register_ability_category' );
/**
 * Register the "Admin Menu Manager" ability category.
 *
 * @return void
 */
function wpa_mm_register_ability_category() {
	wp_register_ability_category(
		'admin-menu-manager',
		array(
			'label'       => __( 'Admin Menu Manager', 'admin-menu-manager' ),
			'description' => __( 'Read and manage the WordPress admin sidebar menu configuration.', 'admin-menu-manager' ),
		)
	);
}

// -------------------------------------------------------------------------
// Register abilities.
// -------------------------------------------------------------------------
add_action( 'wp_abilities_api_init', 'wpa_mm_register_abilities' );
/**
 * Register the Admin Menu Manager abilities (get-config, and gated reset-config).
 *
 * @return void
 */
function wpa_mm_register_abilities() {

	// --- get-config (always available) -----------------------------------

	wp_register_ability(
		'admin-menu-manager/get-config',
		array(
			'label'               => __( 'Get Menu Config', 'admin-menu-manager' ),
			'description'         => __( 'Retrieve the current admin sidebar menu configuration: item order, hidden items, custom groups, and parent assignments.', 'admin-menu-manager' ),
			'category'            => 'admin-menu-manager',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'items'  => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'slug'   => array( 'type' => 'string' ),
								'hidden' => array( 'type' => 'boolean' ),
								'order'  => array( 'type' => 'integer' ),
								'parent' => array( 'type' => array( 'string', 'null' ) ),
							),
						),
					),
					'groups' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'    => array( 'type' => 'string' ),
								'title' => array( 'type' => 'string' ),
								'order' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			),
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'execute_callback'    => function () {
				$config = (array) get_option(
					'wpa_mm_config',
					array(
						'items'  => array(),
						'groups' => array(),
					)
				);
				if ( ! isset( $config['items'] ) ) {
					$config['items']  = array();
				}
				if ( ! isset( $config['groups'] ) ) {
					$config['groups'] = array();
				}
				return $config;
			},
			'meta'                => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	// --- Write abilities (gated by option) --------------------------------

	if ( ! get_option( 'wpa_mm_write_abilities', false ) ) {
		return;
	}

	wp_register_ability(
		'admin-menu-manager/reset-config',
		array(
			'label'               => __( 'Reset Menu Config', 'admin-menu-manager' ),
			'description'         => __( 'Reset the admin sidebar menu to WordPress defaults by deleting all saved configuration. This cannot be undone.', 'admin-menu-manager' ),
			'category'            => 'admin-menu-manager',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'execute_callback'    => function () {
				delete_option( 'wpa_mm_config' );
				return array(
					'success' => true,
					'message' => __( 'Admin menu reset to WordPress defaults.', 'admin-menu-manager' ),
				);
			},
			'meta'                => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);
}
