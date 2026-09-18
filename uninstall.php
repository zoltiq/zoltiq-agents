<?php
/**
 * Zoltiq Chatbot Pro Uninstall
 * 
 * This file is executed when the plugin is uninstalled (deleted) from WordPress.
 * It performs cleanup tasks such as removing custom database tables and data.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

/**
 * Remove custom embedding table.
 *
 * Drops the database table doc embeddings
 */
$table_name = $wpdb->prefix . 'zoltiq_agents_embed';
$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );

/**
 * Remove custom embedding table.
 *
 * Drops the database table tools embeddings
 */
$table_name = $wpdb->prefix . 'zoltiq_tools_embed';
$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );