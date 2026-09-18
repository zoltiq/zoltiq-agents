<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template\Template_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template\Template_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template\Template_File;

defined( 'ABSPATH' ) || exit;

class Create_Block_Template extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-block-template',
			'args' => array(
				'label'               => __( 'Create Block Template', 'zoltiq-agents' ),
				'description'         => __( 'Creates a block template. Defaults to the database (wp_template). Pass source=child_theme to write to the child theme\'s /templates dir, source=theme (with theme_slug) to write to a specific theme folder, or source=plugin (with plugin_slug) to write to a plugin\'s /templates dir.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'        => array(
							'type'        => 'string',
							'description' => __( 'Bare slug (e.g. "index", "single", "404", "page", "front-page", "archive").', 'zoltiq-agents' ),
						),
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'content'     => array(
							'type'        => 'string',
							'description' => __( 'Block markup for the template. Cannot be empty.', 'zoltiq-agents' ),
						),
						'source'      => array(
							'type'    => 'string',
							'enum'    => array( 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => 'db',
						),
						'theme_slug'  => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Theme folder name. Required when source=theme; ignored otherwise. Defaults to the active stylesheet for source=child_theme.', 'zoltiq-agents' ),
						),
						'plugin_slug' => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Plugin folder name. Required when source=plugin.', 'zoltiq-agents' ),
						),
						'status'      => array(
							'type'    => 'string',
							'enum'    => array( 'publish', 'draft', 'private', 'pending' ),
							'default' => 'publish',
						),
					),
					'required'             => array( 'slug', 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'template'  => array( 'type' => 'object' ),
						'warnings'  => array( 'type' => 'array' ),
						'locations' => array( 'type' => 'array' ),
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
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$slug        = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$content     = (string) ( $input['content'] ?? '' );
		$source      = sanitize_text_field( $input['source'] ?? 'db' );
		$theme_slug  = sanitize_key( $input['theme_slug'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );

		if ( '' === $slug || ! Template_File::is_valid_bare_slug( $slug ) ) {
			return array(
				'success' => false,
				'message' => __( 'Slug is invalid. Use lowercase letters, digits, dashes, or underscores.', 'zoltiq-agents' ),
			);
		}

		if ( ! Template_Db::valid_content( $content ) ) {
			return array(
				'success' => false,
				'message' => __( 'Content cannot be empty.', 'zoltiq-agents' ),
			);
		}

		$existing = Template_Detector::locate( $slug, '' !== $theme_slug ? $theme_slug : (string) get_stylesheet() );
		if ( ! empty( $existing ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'A template with slug "%s" already exists. Use template-update or pick a different slug.', 'zoltiq-agents' ), $slug ),
				'locations' => $existing,
			);
		}

		switch ( $source ) {
			case 'db':
				return $this->create_db( $slug, $content, $theme_slug, $input );

			case 'child_theme':
				return $this->create_theme_file( $slug, $content, true, '', $input );

			case 'theme':
				return $this->create_theme_file( $slug, $content, false, $theme_slug, $input );

			case 'plugin':
				return $this->create_plugin_file( $slug, $content, $plugin_slug, $input );
		}

		return array(
			'success' => false,
			'message' => __( 'Unknown source.', 'zoltiq-agents' ),
		);
	}

	private function create_db( string $slug, string $content, string $theme_slug, array $input ): array {
		$theme  = '' !== $theme_slug ? $theme_slug : (string) get_stylesheet();
		$result = Template_Db::create(
			array(
				'slug'        => $slug,
				'title'       => (string) ( $input['title'] ?? $slug ),
				'description' => (string) ( $input['description'] ?? '' ),
				'content'     => $content,
				'theme'       => $theme,
				'status'      => $input['status'] ?? 'publish',
			)
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		$post     = get_post( (int) $result );
		$warnings = array();
		if ( is_multisite() ) {
			$warnings[] = __( 'On multisite, this DB template is scoped to the current site only.', 'zoltiq-agents' );
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Created DB template "%s".', 'zoltiq-agents' ), $slug ),
			'template' => $post ? Template_Db::to_row( $post ) : array(
				'source'  => 'db',
				'slug'    => $slug,
				'post_id' => (int) $result,
			),
			'warnings' => $warnings,
		);
	}

	private function create_theme_file( string $slug, string $content, bool $force_child, string $theme_slug, array $input ): array {
		$warnings = array();

		if ( $force_child ) {
			$dir = Template_File::get_child_theme_dir();
			if ( null === $dir ) {
				return array(
					'success' => false,
					'message' => __( 'No child theme is active. Create a child theme first, or use source=db to store the template in the database instead.', 'zoltiq-agents' ),
				);
			}
		} elseif ( '' !== $theme_slug ) {
			$dir = Template_File::resolve_theme_dir( $theme_slug );
			if ( is_wp_error( $dir ) ) {
				return array(
					'success' => false,
					'message' => $dir->get_error_message(),
				);
			}
			$child = Template_File::get_child_theme_dir();
			if ( null !== $child && $child !== $dir ) {
				$warnings[] = __( 'Writing to the parent theme — your changes will be lost if the theme is updated. Prefer source=child_theme.', 'zoltiq-agents' );
			}
		} else {
			$child = Template_File::get_child_theme_dir();
			$dir   = null !== $child ? $child : Template_File::get_parent_theme_dir();
		}

		$templates_dir = Template_File::ensure_templates_dir( $dir );
		if ( is_wp_error( $templates_dir ) ) {
			return array(
				'success' => false,
				'message' => $templates_dir->get_error_message(),
			);
		}

		$abs = Template_File::resolve_template_path( $dir, $slug );
		if ( is_wp_error( $abs ) ) {
			return array(
				'success' => false,
				'message' => $abs->get_error_message(),
			);
		}

		$bytes = Template_File::write_file( $abs, $content );
		if ( is_wp_error( $bytes ) ) {
			return array(
				'success' => false,
				'message' => $bytes->get_error_message(),
			);
		}

		$theme_name = basename( $dir );
		$child      = Template_File::get_child_theme_dir();
		$is_child   = ( null !== $child && $dir === $child );

		$warnings[] = __( 'Site Editor saves create a DB record that will override this file copy on the next save.', 'zoltiq-agents' );

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Wrote template "%1$s" to %2$s.', 'zoltiq-agents' ), $slug, $abs ),
			'template' => array(
				'source'     => 'theme',
				'theme_type' => $is_child ? 'child' : ( null !== $child ? 'parent' : 'theme' ),
				'theme'      => $theme_name,
				'slug'       => $slug,
				'full_slug'  => $theme_name . '//' . $slug,
				'path'       => $abs,
				'bytes'      => (int) $bytes,
			),
			'warnings' => $warnings,
		);
	}

	private function create_plugin_file( string $slug, string $content, string $plugin_slug, array $input ): array {
		if ( '' === $plugin_slug ) {
			return array(
				'success' => false,
				'message' => __( 'plugin_slug is required when source=plugin.', 'zoltiq-agents' ),
			);
		}

		$plugin = Template_File::resolve_plugin_dir( $plugin_slug );
		if ( is_wp_error( $plugin ) ) {
			return array(
				'success' => false,
				'message' => $plugin->get_error_message(),
			);
		}

		$warnings = array();
		if ( ! $plugin['active'] ) {
			$warnings[] = sprintf( __( 'Plugin "%s" is inactive — the template will not register until the plugin is activated.', 'zoltiq-agents' ), $plugin_slug );
		}

		$templates_dir = Template_File::ensure_templates_dir( $plugin['path'] );
		if ( is_wp_error( $templates_dir ) ) {
			return array(
				'success' => false,
				'message' => $templates_dir->get_error_message(),
			);
		}

		$abs = Template_File::resolve_template_path( $plugin['path'], $slug );
		if ( is_wp_error( $abs ) ) {
			return array(
				'success' => false,
				'message' => $abs->get_error_message(),
			);
		}

		$bytes = Template_File::write_file( $abs, $content );
		if ( is_wp_error( $bytes ) ) {
			return array(
				'success' => false,
				'message' => $bytes->get_error_message(),
			);
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Wrote template "%1$s" to %2$s.', 'zoltiq-agents' ), $slug, $abs ),
			'template' => array(
				'source'        => 'plugin',
				'plugin'        => $plugin_slug,
				'plugin_active' => (bool) $plugin['active'],
				'slug'          => $slug,
				'path'          => $abs,
				'bytes'         => (int) $bytes,
			),
			'warnings' => $warnings,
		);
	}
}
