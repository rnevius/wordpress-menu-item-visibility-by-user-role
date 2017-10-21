<?php
/**
 * Plugin Uninstall
 *
 * Uninstalling this plugins deletes all menu visibility meta.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
 
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array('meta_key' => '_syntarsus_menu_item_visibility') );
