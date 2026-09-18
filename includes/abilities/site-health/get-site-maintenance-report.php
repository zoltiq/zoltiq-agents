<?php

namespace Zoltiq\Agents\Includes\Abilities\SiteHealth;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Site_Maintenance_Report extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-site-maintenance-report',
			'args' => array(
				'label'               => __( 'Site Maintenance Report', 'zoltiq-agents' ),
				'description'         => __( 'One-shot maintenance snapshot: counts of pending core / plugin / theme updates, disk-free bytes on the WP install partition, PHP version, MySQL version, WP version, active theme, and site URL. Safe to poll (read-only).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-site-health',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'          => array( 'type' => 'boolean' ),
						'generated_at'     => array( 'type' => 'integer' ),
						'core_update'      => array( 'type' => 'object' ),
						'plugin_updates'   => array( 'type' => 'integer' ),
						'theme_updates'    => array( 'type' => 'integer' ),
						'disk_free_bytes'  => array( 'type' => 'integer' ),
						'disk_total_bytes' => array( 'type' => 'integer' ),
						'php_version'      => array( 'type' => 'string' ),
						'mysql_version'    => array( 'type' => 'string' ),
						'wp_version'       => array( 'type' => 'string' ),
						'active_theme'     => array( 'type' => 'string' ),
						'site_url'         => array( 'type' => 'string' ),
						'is_multisite'     => array( 'type' => 'boolean' ),
						'message'          => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		unset( $input );
		global $wpdb, $wp_version;

		require_once ABSPATH . 'wp-admin/includes/update.php';
		$core_updates = function_exists( 'get_core_updates' ) ? get_core_updates() : array();
		$core_offer   = array(
			'available' => false,
			'version'   => sanitize_text_field( (string) $wp_version ),
		);
		if ( is_array( $core_updates ) ) {
			foreach ( $core_updates as $offer ) {
				if ( isset( $offer->response ) && 'upgrade' === $offer->response ) {
					$core_offer['available'] = true;
					$core_offer['version']   = sanitize_text_field( (string) ( $offer->version ?? '' ) );
					break;
				}
			}
		}

		$plugin_updates = function_exists( 'get_plugin_updates' ) ? count( (array) get_plugin_updates() ) : 0;
		$theme_updates  = function_exists( 'get_theme_updates' ) ? count( (array) get_theme_updates() ) : 0;

		$disk_free  = @disk_free_space( ABSPATH ); 
		$disk_total = @disk_total_space( ABSPATH ); 

		$theme        = wp_get_theme();
		$active_theme = $theme instanceof \WP_Theme ? sanitize_text_field( (string) $theme->get( 'Name' ) ) : '';

		$mysql_version = '';
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'db_version' ) ) {
			$mysql_version = sanitize_text_field( (string) $wpdb->db_version() );
		}

		return array(
			'success'          => true,
			'generated_at'     => time(),
			'core_update'      => (object) $core_offer,
			'plugin_updates'   => $plugin_updates,
			'theme_updates'    => $theme_updates,
			'disk_free_bytes'  => (int) ( is_float( $disk_free ) ? $disk_free : 0 ),
			'disk_total_bytes' => (int) ( is_float( $disk_total ) ? $disk_total : 0 ),
			'php_version'      => PHP_VERSION,
			'mysql_version'    => $mysql_version,
			'wp_version'       => sanitize_text_field( (string) $wp_version ),
			'active_theme'     => $active_theme,
			'site_url'         => esc_url_raw( (string) home_url( '/' ) ),
			'is_multisite'     => is_multisite(),
			'message'          => sprintf(
				__( 'Pending updates — core: %1$s, plugins: %2$d, themes: %3$d.', 'zoltiq-agents' ),
				$core_offer['available'] ? __( 'yes', 'zoltiq-agents' ) : __( 'no', 'zoltiq-agents' ),
				$plugin_updates,
				$theme_updates
			),
		);
	}
}
