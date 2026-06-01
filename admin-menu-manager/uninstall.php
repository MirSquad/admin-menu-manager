<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpa_mm_config' );
delete_option( 'wpa_mm_write_abilities' );
