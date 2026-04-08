<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes the custom DB table and all stored options so no data
 * is left behind after deletion.
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the A/B test events table.
$table_name = $wpdb->prefix . 'eip_events';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

// Remove stored options.
delete_option( 'eip_settings' );
delete_option( 'eip_db_version' );
