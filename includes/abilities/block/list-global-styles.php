<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_File;

defined( 'ABSPATH' ) || exit;

class List_Global_Styles extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-global-styles',
			'args' => array(
				'label'               => __( 'List Global Styles', 'zoltiq-agents' ),
				'description'         => __( 'Lists Global Styles records across the database (wp_global_styles) and theme.json files in themes and plugins. Each record reports its theme association, which sections are customised, and whether it is the copy WordPress is currently serving.', 'zoltiq-agents' ),
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
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Restrict to a specific theme. Defaults to all themes (for DB) / active theme (for files).', 'zoltiq-agents' ),
						),
						'plugin_slug'     => array(
							'type'    => 'string',
							'default' => '',
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
						'records'      => array( 'type' => 'array' ),
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
		$include_content = ! empty( $input['include_content'] );

		$rows = array();

		if ( 'all' === $source || 'db' === $source ) {
			$rows = array_merge( $rows, $this->collect_db( $theme_slug, $include_content ) );
		}

		if ( 'all' === $source || 'theme' === $source ) {
			$rows = array_merge( $rows, $this->collect_theme( $theme_type, $theme_slug, $include_content ) );
		}

		if ( 'all' === $source || 'plugin' === $source ) {
			$rows = array_merge( $rows, $this->collect_plugins( $plugin_slug, $include_content ) );
		}

		$rows = $this->mark_effective( $rows );

		$warnings = array();
		if ( is_multisite() ) {
			$warnings[] = __( 'On multisite, DB Global Styles records are scoped to the current site only; theme.json files are shared across all sites.', 'zoltiq-agents' );
		}

		return array(
			'success'      => true,
			'records'      => $rows,
			'total'        => count( $rows ),
			'active_theme' => (string) get_stylesheet(),
			'warnings'     => $warnings,
		);
	}

	private function collect_db( string $theme_slug, bool $include_content ): array {
		$posts = Global_Styles_Db::list_all( 500 );
		$rows  = array();
		foreach ( $posts as $post ) {
			$theme = Global_Styles_Db::get_post_theme( $post );
			if ( '' !== $theme_slug && $theme !== $theme_slug ) {
				continue;
			}
			$rows[] = Global_Styles_Db::to_row( $post, $include_content );
		}
		return $rows;
	}

	private function collect_theme( string $theme_type, string $theme_slug, bool $include_content ): array {
		$rows      = array();
		$child_dir = Global_Styles_File::get_child_theme_dir();
		$is_child  = null !== $child_dir;

		if ( '' === $theme_type || 'child' === $theme_type ) {
			if ( $is_child ) {
				$row = $this->theme_row( Global_Styles_File::theme_json_path( $child_dir ), 'child', basename( $child_dir ), $include_content );
				if ( null !== $row ) {
					$rows[] = $row;
				}
			}
		}

		if ( '' === $theme_type || 'parent' === $theme_type || 'theme' === $theme_type ) {
			$parent_dir = '' !== $theme_slug
				? get_theme_root() . '/' . $theme_slug
				: Global_Styles_File::get_parent_theme_dir();
			$tt         = $is_child ? 'parent' : 'theme';
			$row        = $this->theme_row( Global_Styles_File::theme_json_path( $parent_dir ), $tt, basename( $parent_dir ), $include_content );
			if ( null !== $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	private function collect_plugins( string $plugin_slug, bool $include_content ): array {
		$rows = array();
		foreach ( Global_Styles_File::scan_plugins_with_theme_json() as $plugin ) {
			if ( '' !== $plugin_slug && $plugin['slug'] !== $plugin_slug ) {
				continue;
			}
			$row = array(
				'source'        => 'plugin',
				'plugin'        => $plugin['slug'],
				'plugin_active' => (bool) $plugin['active'],
				'path'          => $plugin['path'],
				'writable'      => is_writable( $plugin['path'] ),
			);
			if ( $include_content ) {
				$data = Global_Styles_File::read_json( $plugin['path'] );
				if ( ! is_wp_error( $data ) ) {
					$row['data'] = $data;
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function theme_row( string $path, string $theme_type, string $theme, bool $include_content ): ?array {
		if ( ! is_file( $path ) ) {
			return null;
		}
		$row = array(
			'source'          => 'theme',
			'theme_type'      => $theme_type,
			'theme'           => $theme,
			'path'            => $path,
			'writable'        => is_writable( $path ),
			'is_active_theme' => $theme === (string) get_stylesheet() || $theme === (string) get_template(),
		);
		if ( $include_content ) {
			$data = Global_Styles_File::read_json( $path );
			if ( ! is_wp_error( $data ) ) {
				$row['data'] = $data;
			}
		}
		return $row;
	}

	private function mark_effective( array $rows ): array {
		$by_theme = array();
		foreach ( $rows as $row ) {
			$theme = (string) ( $row['theme'] ?? ( $row['plugin'] ?? '' ) );
			if ( 'plugin' === ( $row['source'] ?? '' ) ) {
				$theme = 'plugin:' . ( $row['plugin'] ?? '' );
			}
			$by_theme[ $theme ][] = $row;
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
		foreach ( $by_theme as $group ) {
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
