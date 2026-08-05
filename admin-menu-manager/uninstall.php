<?php
/**
 * Uninstall handler for Admin Menu Manager.
 *
 * Runs when the plugin is deleted from the WordPress admin; removes the saved
 * menu configuration and the write-abilities option.
 *
 * @package Admin_Menu_Manager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpa_mm_config' );
delete_option( 'wpa_mm_write_abilities' );
