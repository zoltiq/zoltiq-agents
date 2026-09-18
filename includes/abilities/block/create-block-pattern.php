<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Helper;

defined( 'ABSPATH' ) || exit;

class Create_Block_Pattern extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-block-pattern',
			'args' => array(
				'label'               => __( 'Create Block Pattern', 'zoltiq-agents' ),
				'description'         => __( 'Creates a block pattern. Default storage is the database (wp_block CPT). Pass source=theme to write a file under the active theme\'s /patterns (child preferred), or source=plugin with plugin_slug to write under that plugin\'s /patterns. Empty content is refused. Slug clashes at the target source are refused; use block-pattern-update to edit.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source'         => array(
							'type'    => 'string',
							'enum'    => array( 'db', 'theme', 'plugin' ),
							'default' => 'db',
						),
						'slug'           => array(
							'type'        => 'string',
							'description' => __( 'Bare slug (lowercase letters, digits, dashes, underscores).', 'zoltiq-agents' ),
						),
						'title'          => array( 'type' => 'string' ),
						'content'        => array(
							'type'        => 'string',
							'description' => __( 'Block markup body. Must be non-empty.', 'zoltiq-agents' ),
						),
						'description'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'viewport_width' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'inserter'       => array(
							'type'    => 'string',
							'enum'    => array( 'yes', 'no' ),
							'default' => 'yes',
						),
						'categories'     => array(
							'type'    => 'string',
							'default' => '',
						),
						'keywords'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'block_types'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'post_types'     => array(
							'type'    => 'string',
							'default' => '',
						),
						'template_types' => array(
							'type'    => 'string',
							'default' => '',
						),

						'theme_slug'     => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Theme folder for source=theme. Defaults to child stylesheet when present, otherwise the active theme.', 'zoltiq-agents' ),
						),

						'plugin_slug'    => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Plugin folder for source=plugin.', 'zoltiq-agents' ),
						),

						'status'         => array(
							'type'    => 'string',
							'enum'    => array( 'publish', 'draft', 'private', 'pending' ),
							'default' => 'publish',
						),
						'sync_status'    => array(
							'type'    => 'string',
							'enum'    => array( 'synced', 'unsynced' ),
							'default' => 'unsynced',
						),
					),
					'required'             => array( 'slug', 'title', 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'message'    => array( 'type' => 'string' ),
						'error_code' => array( 'type' => 'string' ),
						'pattern'    => array( 'type' => 'object' ),
					),
					'required'   => array( 'success' ),
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
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$blocked = File_Mods_Guard::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}

		$source = sanitize_text_field( $input['source'] ?? 'db' );
		$slug   = sanitize_title( (string) ( $input['slug'] ?? '' ) );

		if ( '' === $slug || ! Pattern_Helper::is_valid_bare_slug( $slug ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'slug must be lowercase letters, digits, dashes, or underscores.', 'zoltiq-agents' ),
				'error_code' => 'invalid_slug',
			);
		}

		$content = (string) ( $input['content'] ?? '' );
		if ( ! Pattern_Helper::is_valid_content( $content ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'content is required and may not be empty.', 'zoltiq-agents' ),
				'error_code' => 'empty_content',
			);
		}

		switch ( $source ) {
			case 'db':
				return $this->create_db( $slug, $input );
			case 'theme':
				return $this->create_theme( $slug, $input );
			case 'plugin':
				return $this->create_plugin( $slug, $input );
		}
		return array(
			'success'    => false,
			'message'    => __( 'Invalid source.', 'zoltiq-agents' ),
			'error_code' => 'invalid_source',
		);
	}

	private function create_db( string $slug, array $input ): array {
		$result = Pattern_Db::create(
			array(
				'slug'        => $slug,
				'title'       => sanitize_text_field( (string) $input['title'] ),
				'description' => sanitize_text_field( (string) ( $input['description'] ?? '' ) ),
				'content'     => (string) $input['content'],
				'status'      => sanitize_text_field( (string) ( $input['status'] ?? 'publish' ) ),
				'sync_status' => sanitize_text_field( (string) ( $input['sync_status'] ?? 'unsynced' ) ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success'    => false,
				'message'    => $result->get_error_message(),
				'error_code' => $result->get_error_code(),
			);
		}

		$post = get_post( (int) $result );
		return array(
			'success' => true,
			'message' => __( 'Pattern created in the database.', 'zoltiq-agents' ),
			'pattern' => $post ? Pattern_Db::to_row( $post ) : array(
				'source'  => 'db',
				'slug'    => $slug,
				'post_id' => (int) $result,
			),
		);
	}

	private function create_theme( string $slug, array $input ): array {
		$theme_slug = sanitize_text_field( $input['theme_slug'] ?? '' );

		if ( '' === $theme_slug ) {
			$child = Pattern_Helper::get_child_theme_dir();
			$dir   = null !== $child ? $child : Pattern_Helper::get_parent_theme_dir();
		} else {
			$dir = Pattern_Helper::resolve_theme_dir( $theme_slug );
			if ( is_wp_error( $dir ) ) {
				return array(
					'success'    => false,
					'message'    => $dir->get_error_message(),
					'error_code' => $dir->get_error_code(),
				);
			}
		}

		return $this->write_file(
			$dir,
			$slug,
			basename( $dir ),
			$input,
			__( 'Pattern created in theme /patterns.', 'zoltiq-agents' ),
			array(
				'source' => 'theme',
				'theme'  => basename( $dir ),
			)
		);
	}

	private function create_plugin( string $slug, array $input ): array {
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );
		$plugin      = Pattern_Helper::resolve_plugin_dir( $plugin_slug );
		if ( is_wp_error( $plugin ) ) {
			return array(
				'success'    => false,
				'message'    => $plugin->get_error_message(),
				'error_code' => $plugin->get_error_code(),
			);
		}

		$response = $this->write_file(
			$plugin['path'],
			$slug,
			$plugin_slug,
			$input,
			__( 'Pattern created in plugin /patterns.', 'zoltiq-agents' ),
			array(
				'source'        => 'plugin',
				'plugin'        => $plugin_slug,
				'plugin_active' => $plugin['active'],
			)
		);

		if ( ! empty( $response['success'] ) && ! $plugin['active'] ) {
			$response['message'] .= ' ' . __( '(Warning: plugin is inactive; this pattern will not appear in the inserter until the plugin is activated.)', 'zoltiq-agents' );
		}

		return $response;
	}

	private function write_file( string $container_dir, string $slug, string $prefix, array $input, string $ok_message, array $base_extra ): array {
		$abs = Pattern_Helper::resolve_pattern_path( $container_dir, $slug );
		if ( is_wp_error( $abs ) ) {
			return array(
				'success'    => false,
				'message'    => $abs->get_error_message(),
				'error_code' => $abs->get_error_code(),
			);
		}

		$patterns_dir = dirname( $abs );
		if ( ! is_dir( $patterns_dir ) && ! wp_mkdir_p( $patterns_dir ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'Could not create /patterns directory.', 'zoltiq-agents' ),
				'error_code' => 'mkdir_failed',
			);
		}

		if ( file_exists( $abs ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'Pattern file already exists. Use block-pattern-update to overwrite.', 'zoltiq-agents' ),
				'error_code' => 'slug_conflict',
			);
		}

		$headers  = $this->build_headers( $prefix, $slug, $input );
		$file_str = Pattern_Helper::build_file( $headers, (string) $input['content'] );

		$bytes = file_put_contents( $abs, $file_str ); 
		if ( false === $bytes ) {
			return array(
				'success'    => false,
				'message'    => __( 'Could not write pattern file. Check file permissions.', 'zoltiq-agents' ),
				'error_code' => 'file_not_writable',
			);
		}

		return array(
			'success' => true,
			'message' => $ok_message,
			'pattern' => array_merge(
				$base_extra,
				array(
					'slug'    => $slug,
					'path'    => $abs,
					'headers' => $headers,
				)
			),
		);
	}

	private function build_headers( string $prefix, string $slug, array $input ): array {
		return array(
			'Title'          => sanitize_text_field( (string) $input['title'] ),
			'Slug'           => Pattern_Helper::build_full_slug( $prefix, $slug ),
			'Description'    => sanitize_text_field( (string) ( $input['description'] ?? '' ) ),
			'Viewport Width' => isset( $input['viewport_width'] ) ? (string) (int) $input['viewport_width'] : '',
			'Inserter'       => sanitize_text_field( (string) ( $input['inserter'] ?? 'yes' ) ),
			'Categories'     => sanitize_text_field( (string) ( $input['categories'] ?? '' ) ),
			'Keywords'       => sanitize_text_field( (string) ( $input['keywords'] ?? '' ) ),
			'Block Types'    => sanitize_text_field( (string) ( $input['block_types'] ?? '' ) ),
			'Post Types'     => sanitize_text_field( (string) ( $input['post_types'] ?? '' ) ),
			'Template Types' => sanitize_text_field( (string) ( $input['template_types'] ?? '' ) ),
		);
	}
}
