<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Refresh_Site_Editor_Context extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/refresh-site-editor-context',
			'args' => array(
				'label'               => __( 'Refresh Site Editor Context', 'zoltiq-agents' ),
				'description'         => __( 'Flush block-template + theme.json related caches so the next call to site-editor-get-context returns fresh state. Invalidates: `wp_theme_features`, `theme_json` cache group entries, and post cache for `wp_template` + `wp_template_part` post types.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
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
						'success'      => array( 'type' => 'boolean' ),
						'refreshed_at' => array( 'type' => 'integer' ),
						'invalidated'  => array( 'type' => 'array' ),
						'message'      => array( 'type' => 'string' ),
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
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		unset( $input );

		$invalidated = array();

		if ( function_exists( 'wp_cache_delete_group' ) ) {
			wp_cache_delete_group( 'theme_json' );
			$invalidated[] = 'theme_json';
		}
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache();
			$invalidated[] = 'themes';
		}
		if ( function_exists( 'clean_post_cache' ) ) {
			foreach ( array( 'wp_template', 'wp_template_part', 'wp_block' ) as $ptype ) {
				$ids = get_posts(
					array(
						'post_type'      => $ptype,
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					)
				);
				if ( is_array( $ids ) ) {
					foreach ( $ids as $id ) {
						clean_post_cache( (int) $id );
					}
				}
				$invalidated[] = $ptype;
			}
		}
		if ( class_exists( '\WP_Theme_JSON_Resolver' ) && method_exists( '\WP_Theme_JSON_Resolver', 'clean_cached_data' ) ) {
			\WP_Theme_JSON_Resolver::clean_cached_data();
			$invalidated[] = 'wp_theme_json_resolver';
		}

		return array(
			'success'      => true,
			'refreshed_at' => time(),
			'invalidated'  => $invalidated,
			'message'      => sprintf( __( 'Site Editor context invalidated across %d cache source(s).', 'zoltiq-agents' ), count( $invalidated ) ),
		);
	}
}
