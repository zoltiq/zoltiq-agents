<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_File;

defined( 'ABSPATH' ) || exit;

class List_Block_Style_Variations extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-block-style-variations',
			'args' => array(
				'label'               => __( 'List Block Style Variations', 'zoltiq-agents' ),
				'description'         => __( 'Lists Block Style Variations across the database (wp_global_styles) and theme/plugin /styles directories. Each variation reports its theme, slug, customised sections, and whether it is the active variation.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source'          => array(
							'type'    => 'string',
							'enum'    => array( 'all', 'db', 'theme', 'plugin' ),
							'default' => 'all',
						),
						'theme_type'      => array(
							'type'    => 'string',
							'enum'    => array( '', 'child', 'parent', 'theme' ),
							'default' => '',
						),
						'theme_slug'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'plugin_slug'     => array(
							'type'    => 'string',
							'default' => '',
						),
						'slug'            => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Optional exact slug to look up.', 'zoltiq-agents' ),
						),
						'include_content' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'      => array( 'type' => 'boolean' ),
						'variations'   => array( 'type' => 'array' ),
						'total'        => array( 'type' => 'integer' ),
						'active_theme' => array( 'type' => 'string' ),
						'warnings'     => array( 'type' => 'array' ),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$source          = sanitize_text_field( $input['source'] ?? 'all' );
		$theme_type      = sanitize_text_field( $input['theme_type'] ?? '' );
		$theme_slug      = sanitize_key( $input['theme_slug'] ?? '' );
		$plugin_slug     = sanitize_key( $input['plugin_slug'] ?? '' );
		$slug_filter     = sanitize_title( $input['slug'] ?? '' );
		$include_content = ! empty( $input['include_content'] );

		$rows = array();

		if ( 'all' === $source || 'db' === $source ) {
			$rows = array_merge( $rows, $this->collect_db( $theme_slug, $slug_filter, $include_content ) );
		}

		if ( 'all' === $source || 'theme' === $source ) {
			$rows = array_merge( $rows, $this->collect_theme( $theme_type, $theme_slug, $slug_filter, $include_content ) );
		}

		if ( 'all' === $source || 'plugin' === $source ) {
			$rows = array_merge( $rows, $this->collect_plugins( $plugin_slug, $slug_filter, $include_content ) );
		}

		$rows = $this->mark_effective( $rows );

		$warnings = array();
		if ( is_multisite() ) {
			$warnings[] = __( 'On multisite, DB variation rows are scoped to the current site only; /styles files are shared across all sites.', 'zoltiq-agents' );
		}

		return array(
			'success'      => true,
			'variations'   => $rows,
			'total'        => count( $rows ),
			'active_theme' => (string) get_stylesheet(),
			'warnings'     => $warnings,
		);
	}

	private function collect_db( string $theme, string $slug_filter, bool $include_content ): array {
		$posts = Variation_Db::list_all( $theme, 500 );
		$rows  = array();
		foreach ( $posts as $post ) {
			if ( '' !== $slug_filter && (string) $post->post_name !== $slug_filter ) {
				continue;
			}
			$rows[] = Variation_Db::to_row( $post, $include_content );
		}
		return $rows;
	}

	private function collect_theme( string $theme_type, string $theme_slug, string $slug_filter, bool $include_content ): array {
		$rows      = array();
		$child_dir = Variation_File::get_child_theme_dir();
		$is_child  = null !== $child_dir;

		if ( '' === $theme_type || 'child' === $theme_type ) {
			if ( $is_child ) {
				$rows = array_merge( $rows, $this->scan_container( $child_dir, 'theme', 'child', basename( $child_dir ), $slug_filter, $include_content ) );
			}
		}

		if ( '' === $theme_type || 'parent' === $theme_type || 'theme' === $theme_type ) {
			$parent_dir = '' !== $theme_slug ? get_theme_root() . '/' . $theme_slug : Variation_File::get_parent_theme_dir();
			$tt         = $is_child ? 'parent' : 'theme';
			$rows       = array_merge( $rows, $this->scan_container( $parent_dir, 'theme', $tt, basename( $parent_dir ), $slug_filter, $include_content ) );
		}

		return $rows;
	}

	private function collect_plugins( string $plugin_slug, string $slug_filter, bool $include_content ): array {
		$rows = array();
		foreach ( Variation_File::scan_plugins_with_styles() as $plugin ) {
			if ( '' !== $plugin_slug && $plugin['slug'] !== $plugin_slug ) {
				continue;
			}
			foreach ( Variation_File::scan_variations_in_dir( dirname( $plugin['path'] ) ) as $file_row ) {
				if ( '' !== $slug_filter && $file_row['slug'] !== $slug_filter ) {
					continue;
				}
				$row = array(
					'source'        => 'plugin',
					'plugin'        => $plugin['slug'],
					'plugin_active' => (bool) $plugin['active'],
					'slug'          => $file_row['slug'],
					'path'          => $file_row['path'],
					'writable'      => $file_row['writable'],
				);
				if ( $include_content ) {
					$data = Variation_File::read_json( $file_row['path'] );
					if ( ! is_wp_error( $data ) ) {
						$row['data'] = $data;
					}
				}
				$rows[] = $row;
			}
		}
		return $rows;
	}

	private function scan_container( string $container, string $source, string $theme_type, string $theme_name, string $slug_filter, bool $include_content ): array {
		$rows  = array();
		$files = Variation_File::scan_variations_in_dir( $container );
		foreach ( $files as $file_row ) {
			if ( '' !== $slug_filter && $file_row['slug'] !== $slug_filter ) {
				continue;
			}
			$row = array(
				'source'     => $source,
				'theme_type' => $theme_type,
				'theme'      => $theme_name,
				'slug'       => $file_row['slug'],
				'path'       => $file_row['path'],
				'writable'   => $file_row['writable'],
			);
			if ( $include_content ) {
				$data = Variation_File::read_json( $file_row['path'] );
				if ( ! is_wp_error( $data ) ) {
					$row['data'] = $data;
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function mark_effective( array $rows ): array {
		$by_slug = array();
		foreach ( $rows as $row ) {
			$slug = (string) ( $row['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$by_slug[ $slug ][] = $row;
		}

		$priority = static function ( array $row ): int {
			if ( 'db' === ( $row['source'] ?? '' ) ) {
				return 0;
			}
			if ( 'theme' === ( $row['source'] ?? '' ) ) {
				return 'child' === ( $row['theme_type'] ?? '' ) ? 1 : 2;
			}
			return 3;
		};

		$out = array();
		foreach ( $by_slug as $group ) {
			usort(
				$group,
				static function ( $a, $b ) use ( $priority ): int {
					return $priority( $a ) <=> $priority( $b );
				}
			);
			foreach ( $group as $i => $row ) {
				$row['effective'] = ( 0 === $i );
				if ( false === $row['effective'] ) {
					$winner               = $group[0];
					$row['overridden_by'] = ( $winner['source'] ?? '' ) . ( isset( $winner['theme_type'] ) ? ':' . $winner['theme_type'] : '' );
				}
				$out[] = $row;
			}
		}
		return $out;
	}
}
