<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template\Template_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template\Template_File;

defined( 'ABSPATH' ) || exit;

class List_Block_Templates extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-block-templates',
			'args' => array(
				'label'               => __( 'List Block Templates', 'zoltiq-agents' ),
				'description'         => __( 'Lists block templates across the database (wp_template), the active theme\'s /templates/*.html, the parent theme, and installed plugin /templates dirs. Filter by source, theme_type, plugin_slug, or exact slug.', 'zoltiq-agents' ),
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
							'description' => __( 'Restrict file scan to a specific theme folder. Defaults to active theme.', 'zoltiq-agents' ),
						),
						'plugin_slug'     => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Restrict plugin scan to one plugin folder name.', 'zoltiq-agents' ),
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
						'success'   => array( 'type' => 'boolean' ),
						'templates' => array( 'type' => 'array' ),
						'total'     => array( 'type' => 'integer' ),
						'theme'     => array( 'type' => 'string' ),
						'message'   => array( 'type' => 'string' ),
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

		$active_theme = '' !== $theme_slug ? $theme_slug : (string) get_stylesheet();

		$rows = array();

		if ( 'all' === $source || 'db' === $source ) {
			$rows = array_merge( $rows, $this->collect_db( $active_theme, $slug_filter, $include_content ) );
		}

		if ( 'all' === $source || 'theme' === $source ) {
			$rows = array_merge( $rows, $this->collect_theme( $theme_type, $theme_slug, $slug_filter, $include_content ) );
		}

		if ( 'all' === $source || 'plugin' === $source ) {
			$rows = array_merge( $rows, $this->collect_plugins( $plugin_slug, $slug_filter, $include_content ) );
		}

		$rows = $this->mark_effective( $rows );

		return array(
			'success'   => true,
			'templates' => $rows,
			'total'     => count( $rows ),
			'theme'     => $active_theme,
		);
	}

	private function collect_db( string $theme, string $slug_filter, bool $include_content ): array {
		$posts = Template_Db::list_all( $theme, 500 );
		$rows  = array();
		foreach ( $posts as $post ) {
			if ( '' !== $slug_filter && (string) $post->post_name !== $slug_filter ) {
				continue;
			}
			$row = Template_Db::to_row( $post );
			if ( ! $include_content ) {
				unset( $row['content'] );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function collect_theme( string $theme_type, string $theme_slug, string $slug_filter, bool $include_content ): array {
		$rows = array();

		$child_dir = Template_File::get_child_theme_dir();
		$is_child  = null !== $child_dir;

		if ( '' === $theme_type || 'child' === $theme_type ) {
			if ( $is_child ) {
				$rows = array_merge( $rows, $this->scan_dir( $child_dir, 'child', basename( $child_dir ), $slug_filter, $include_content ) );
			}
		}

		if ( '' === $theme_type || 'parent' === $theme_type || 'theme' === $theme_type ) {
			$parent_dir = '' !== $theme_slug ? get_theme_root() . '/' . $theme_slug : Template_File::get_parent_theme_dir();
			$tt         = $is_child ? 'parent' : 'theme';
			$rows       = array_merge( $rows, $this->scan_dir( $parent_dir, $tt, basename( $parent_dir ), $slug_filter, $include_content ) );
		}

		return $rows;
	}

	private function collect_plugins( string $plugin_slug, string $slug_filter, bool $include_content ): array {
		$rows = array();
		foreach ( Template_File::scan_plugins_with_templates() as $plugin ) {
			if ( '' !== $plugin_slug && $plugin['slug'] !== $plugin_slug ) {
				continue;
			}
			$files = glob( $plugin['path'] . '/*.html' );
			if ( ! is_array( $files ) ) {
				continue;
			}
			foreach ( $files as $file ) {
				$bare = preg_replace( '/\.html$/i', '', basename( $file ) );
				if ( '' !== $slug_filter && $bare !== $slug_filter ) {
					continue;
				}
				$rows[] = $this->file_row( 'plugin', '', $bare, $file, $plugin['slug'], (bool) $plugin['active'], $include_content );
			}
		}
		return $rows;
	}

	private function scan_dir( string $container, string $theme_type, string $theme, string $slug_filter, bool $include_content ): array {
		$rows = array();
		$dir  = Template_File::templates_dir( $container );
		if ( ! is_dir( $dir ) ) {
			return $rows;
		}
		$files = glob( $dir . '/*.html' );
		if ( ! is_array( $files ) ) {
			return $rows;
		}
		foreach ( $files as $file ) {
			$bare = preg_replace( '/\.html$/i', '', basename( $file ) );
			if ( '' !== $slug_filter && $bare !== $slug_filter ) {
				continue;
			}
			$rows[] = $this->file_row( 'theme', $theme_type, $bare, $file, $theme, true, $include_content );
		}
		return $rows;
	}

	private function file_row( string $source, string $theme_type, string $slug, string $path, string $owner, bool $active, bool $include_content ): array {
		$row = array(
			'source'   => $source,
			'slug'     => (string) $slug,
			'path'     => $path,
			'writable' => is_writable( $path ),
		);
		if ( 'theme' === $source ) {
			$row['theme']      = $owner;
			$row['theme_type'] = $theme_type;
			$row['full_slug']  = $owner . '//' . $slug;
		} else {
			$row['plugin']        = $owner;
			$row['plugin_active'] = $active;
		}
		if ( $include_content ) {
			$contents = Template_File::read_file( $path );
			if ( ! is_wp_error( $contents ) ) {
				$row['content'] = $contents;
			}
		}
		return $row;
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
			if ( 'plugin' === ( $row['source'] ?? '' ) ) {
				return 3;
			}
			return 4;
		};

		$out = array();
		foreach ( $by_slug as $candidates ) {
			usort(
				$candidates,
				static function ( $a, $b ) use ( $priority ): int {
					return $priority( $a ) <=> $priority( $b );
				}
			);
			$winner = $candidates[0];
			foreach ( $candidates as $i => $candidate ) {
				$candidate['effective'] = ( $i === 0 );
				if ( false === $candidate['effective'] ) {
					$candidate['overridden_by'] = ( $winner['source'] ?? '' ) . ( isset( $winner['theme_type'] ) ? ':' . $winner['theme_type'] : '' );
				}
				$out[] = $candidate;
			}
		}
		return $out;
	}
}
