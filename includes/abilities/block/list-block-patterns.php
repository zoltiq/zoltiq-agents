<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Helper;

defined( 'ABSPATH' ) || exit;

class List_Block_Patterns extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-block-patterns',
			'args' => array(
				'label'               => __( 'List Block Patterns', 'zoltiq-agents' ),
				'description'         => __( 'Lists block patterns across all storage layers — database (wp_block CPT), theme /patterns folders (child + parent), and plugin /patterns folders. Pass "slug" to find every location that holds a specific pattern (detection step for Update / Delete).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source'      => array(
							'type'    => 'string',
							'enum'    => array( 'all', 'db', 'theme', 'plugin' ),
							'default' => 'all',
						),
						'slug'        => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Restrict to this slug (acts as detection across sources).', 'zoltiq-agents' ),
						),
						'theme_slug'  => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Restrict theme scan to this theme folder. Defaults to scanning the active stylesheet + parent.', 'zoltiq-agents' ),
						),
						'plugin_slug' => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Restrict plugin scan to this plugin folder.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'  => array( 'type' => 'boolean' ),
						'patterns' => array( 'type' => 'array' ),
						'total'    => array( 'type' => 'integer' ),
						'message'  => array( 'type' => 'string' ),
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
		$source      = sanitize_text_field( $input['source'] ?? 'all' );
		$slug_filter = sanitize_title( $input['slug'] ?? '' );
		$theme_slug  = sanitize_text_field( $input['theme_slug'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );

		$rows = array();

		if ( '' !== $slug_filter ) {
			$rows = Pattern_Detector::locate( $slug_filter );
			$rows = $this->apply_source_filter( $rows, $source, $theme_slug, $plugin_slug );
			return array(
				'success'  => true,
				'patterns' => $rows,
				'total'    => count( $rows ),
				'message'  => empty( $rows )
					? __( 'No pattern with this slug was found at the requested source(s).', 'zoltiq-agents' )
					: '',
			);
		}

		if ( 'all' === $source || 'db' === $source ) {
			foreach ( Pattern_Db::list_all() as $post ) {
				$rows[] = array(
					'source'  => 'db',
					'slug'    => (string) $post->post_name,
					'title'   => (string) $post->post_title,
					'post_id' => (int) $post->ID,
					'status'  => (string) $post->post_status,
				);
			}
		}

		if ( 'all' === $source || 'theme' === $source ) {
			$rows = array_merge( $rows, $this->scan_themes( $theme_slug ) );
		}

		if ( 'all' === $source || 'plugin' === $source ) {
			$rows = array_merge( $rows, $this->scan_plugins( $plugin_slug ) );
		}

		return array(
			'success'  => true,
			'patterns' => $rows,
			'total'    => count( $rows ),
		);
	}

	private function scan_themes( string $theme_slug ): array {
		$rows = array();

		$targets = array();
		if ( '' !== $theme_slug ) {
			$dir = Pattern_Helper::resolve_theme_dir( $theme_slug );
			if ( ! is_wp_error( $dir ) ) {
				$targets[] = array(
					'dir'        => $dir,
					'theme_type' => 'theme',
					'theme'      => $theme_slug,
				);
			}
		} else {
			$child = Pattern_Helper::get_child_theme_dir();
			if ( null !== $child ) {
				$targets[] = array(
					'dir'        => $child,
					'theme_type' => 'child',
					'theme'      => basename( $child ),
				);
			}
			$parent            = Pattern_Helper::get_parent_theme_dir();
			$is_actually_child = ( null !== $child );
			$targets[]         = array(
				'dir'        => $parent,
				'theme_type' => $is_actually_child ? 'parent' : 'theme',
				'theme'      => basename( $parent ),
			);
		}

		foreach ( $targets as $target ) {
			$patterns_dir = $target['dir'] . '/patterns';
			if ( ! is_dir( $patterns_dir ) ) {
				continue;
			}
			foreach ( glob( $patterns_dir . '/*.php' ) ?: array() as $file ) {
				$rows[] = array(
					'source'     => 'theme',
					'theme_type' => $target['theme_type'],
					'theme'      => $target['theme'],
					'slug'       => preg_replace( '/\.php$/i', '', basename( $file ) ),
					'path'       => $file,
					'writable'   => is_writable( $file ),
				);
			}
		}

		return $rows;
	}

	private function scan_plugins( string $plugin_slug ): array {
		$rows = array();

		$plugins = Pattern_Helper::scan_plugins_with_patterns();
		if ( '' !== $plugin_slug ) {
			$plugins = array_values(
				array_filter(
					$plugins,
					static function ( $p ) use ( $plugin_slug ): bool {
						return $p['slug'] === $plugin_slug;
					}
				)
			);
		}

		foreach ( $plugins as $plugin ) {
			foreach ( glob( $plugin['path'] . '/*.php' ) ?: array() as $file ) {
				$rows[] = array(
					'source'        => 'plugin',
					'plugin'        => $plugin['slug'],
					'plugin_active' => $plugin['active'],
					'slug'          => preg_replace( '/\.php$/i', '', basename( $file ) ),
					'path'          => $file,
					'writable'      => is_writable( $file ),
				);
			}
		}

		return $rows;
	}

	private function apply_source_filter( array $rows, string $source, string $theme_slug, string $plugin_slug ): array {
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $source, $theme_slug, $plugin_slug ): bool {
					if ( 'all' !== $source && ( $row['source'] ?? '' ) !== $source ) {
						return false;
					}
					if ( 'theme' === $source && '' !== $theme_slug && ( $row['theme'] ?? '' ) !== $theme_slug ) {
						return false;
					}
					if ( 'plugin' === $source && '' !== $plugin_slug && ( $row['plugin'] ?? '' ) !== $plugin_slug ) {
						return false;
					}
					return true;
				}
			)
		);
	}
}
