<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template\Template_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template\Template_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template\Template_File;

defined( 'ABSPATH' ) || exit;

class Update_Block_Template extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-block-template',
			'args' => array(
				'label'               => __( 'Update Block Template', 'zoltiq-agents' ),
				'description'         => __( 'Updates an existing block template. Detects the location automatically; pass source / theme_type / plugin_slug to disambiguate. Supports rename via new_slug and cross-source migration via migrate_to. Refuses parent-theme writes — copy to child or DB first.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'          => array(
							'type'        => 'string',
							'description' => __( 'Existing slug to update.', 'zoltiq-agents' ),
						),
						'source'        => array(
							'type'    => 'string',
							'enum'    => array( '', 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => '',
						),
						'theme_type'    => array(
							'type'    => 'string',
							'enum'    => array( '', 'child', 'parent', 'theme' ),
							'default' => '',
						),
						'plugin_slug'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'theme'         => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Theme hint for DB row lookup.', 'zoltiq-agents' ),
						),
						'title'         => array( 'type' => 'string' ),
						'description'   => array( 'type' => 'string' ),
						'content'       => array(
							'type'        => 'string',
							'description' => __( 'New content. Empty/whitespace-only content is rejected.', 'zoltiq-agents' ),
						),
						'status'        => array(
							'type' => 'string',
							'enum' => array( 'publish', 'draft', 'private', 'pending' ),
						),
						'new_slug'      => array(
							'type'        => 'string',
							'description' => __( 'Rename to this slug. For file-based templates, renames the .html file. For DB templates, updates post_name.', 'zoltiq-agents' ),
						),
						'migrate_to'    => array(
							'type'        => 'string',
							'enum'        => array( '', 'db', 'child_theme' ),
							'default'     => '',
							'description' => __( '"db" = copy file content into a wp_template row. "child_theme" = copy DB or parent-theme content into the child theme\'s /templates. Combine with delete_source to remove the original.', 'zoltiq-agents' ),
						),
						'delete_source' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'After migration, remove the source copy. Parent-theme and plugin files are never deleted.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'template'   => array( 'type' => 'object' ),
						'migrated'   => array( 'type' => 'boolean' ),
						'warnings'   => array( 'type' => 'array' ),
						'locations'  => array( 'type' => 'array' ),
						'candidates' => array( 'type' => 'array' ),
						'message'    => array( 'type' => 'string' ),
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
		$slug          = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$source        = sanitize_text_field( $input['source'] ?? '' );
		$theme_type    = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug   = sanitize_key( $input['plugin_slug'] ?? '' );
		$theme_hint    = sanitize_key( $input['theme'] ?? '' );
		$migrate_to    = sanitize_text_field( $input['migrate_to'] ?? '' );
		$delete_source = ! empty( $input['delete_source'] );

		if ( '' === $slug ) {
			return array(
				'success' => false,
				'message' => __( 'Slug is required.', 'zoltiq-agents' ),
			);
		}

		$locations = Template_Detector::locate( $slug, $theme_hint );

		if ( empty( $locations ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'No template with slug "%s" was found. Use template-create.', 'zoltiq-agents' ), $slug ),
				'locations' => array(),
			);
		}

		$selected = Template_Detector::select( $locations, $source, $theme_type, $plugin_slug );
		if ( is_wp_error( $selected ) ) {
			$data = $selected->get_error_data();
			return array(
				'success'    => false,
				'message'    => $selected->get_error_message(),
				'locations'  => $locations,
				'candidates' => is_array( $data ) ? ( $data['locations'] ?? array() ) : array(),
			);
		}

		if ( '' !== $migrate_to ) {
			return $this->migrate( $selected, $migrate_to, $delete_source, $input );
		}

		if ( 'theme' === ( $selected['source'] ?? '' ) && 'parent' === ( $selected['theme_type'] ?? '' ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'Refusing to edit the parent theme directly. Re-run with migrate_to=child_theme to copy the template into the active child theme first, or migrate_to=db to store it in the database.', 'zoltiq-agents' ),
				'locations' => $locations,
			);
		}

		switch ( $selected['source'] ?? '' ) {
			case 'db':
				return $this->update_db( $selected, $input );

			case 'theme':
			case 'plugin':
				return $this->update_file( $selected, $input );
		}

		return array(
			'success' => false,
			'message' => __( 'Unknown source.', 'zoltiq-agents' ),
		);
	}

	private function update_db( array $loc, array $input ): array {
		$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'Database template not found.', 'zoltiq-agents' ),
			);
		}

		$data = $this->collect_fields( $input );
		if ( isset( $input['new_slug'] ) && '' !== $input['new_slug'] ) {
			$data['new_slug'] = (string) $input['new_slug'];
		}

		$result = Template_Db::update( $post, $data );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		$updated = get_post( (int) $result );
		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Updated DB template "%s".', 'zoltiq-agents' ), $updated ? $updated->post_name : '' ),
			'template' => $updated ? Template_Db::to_row( $updated ) : array(),
			'warnings' => array(),
		);
	}

	private function update_file( array $loc, array $input ): array {
		$path = (string) ( $loc['path'] ?? '' );
		if ( '' === $path || ! is_file( $path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Template file not found.', 'zoltiq-agents' ),
			);
		}

		$warnings = array();
		if ( 'plugin' === ( $loc['source'] ?? '' ) && false === ( $loc['plugin_active'] ?? true ) ) {
			$warnings[] = sprintf( __( 'Plugin "%s" is inactive — your edit will only take effect once the plugin is activated.', 'zoltiq-agents' ), $loc['plugin'] ?? '' );
		}

		$content = array_key_exists( 'content', $input ) ? (string) $input['content'] : null;
		if ( null !== $content && ! Template_Db::valid_content( $content ) ) {
			return array(
				'success' => false,
				'message' => __( 'Content cannot be empty.', 'zoltiq-agents' ),
			);
		}

		if ( null !== $content ) {
			$bytes = Template_File::write_file( $path, $content );
			if ( is_wp_error( $bytes ) ) {
				return array(
					'success' => false,
					'message' => $bytes->get_error_message(),
				);
			}
		}

		$final_path = $path;
		$final_slug = (string) ( $loc['slug'] ?? '' );
		if ( ! empty( $input['new_slug'] ) ) {
			$new_slug = sanitize_title( (string) $input['new_slug'] );
			if ( '' === $new_slug || ! Template_File::is_valid_bare_slug( $new_slug ) ) {
				return array(
					'success' => false,
					'message' => __( 'new_slug is invalid.', 'zoltiq-agents' ),
				);
			}
			$dir     = dirname( dirname( $path ) ); 
			$new_abs = Template_File::resolve_template_path( $dir, $new_slug );
			if ( is_wp_error( $new_abs ) ) {
				return array(
					'success' => false,
					'message' => $new_abs->get_error_message(),
				);
			}
			if ( file_exists( $new_abs ) && $new_abs !== $path ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Target slug already has a file at %s.', 'zoltiq-agents' ), $new_abs ),
				);
			}
			if ( ! @rename( $path, $new_abs ) ) { 
				return array(
					'success' => false,
					'message' => __( 'Failed to rename template file.', 'zoltiq-agents' ),
				);
			}
			$final_path = $new_abs;
			$final_slug = $new_slug;
		}

		$template = array(
			'source'   => (string) ( $loc['source'] ?? '' ),
			'slug'     => $final_slug,
			'path'     => $final_path,
			'writable' => is_writable( $final_path ),
		);
		if ( 'theme' === ( $loc['source'] ?? '' ) ) {
			$template['theme']      = (string) ( $loc['theme'] ?? '' );
			$template['theme_type'] = (string) ( $loc['theme_type'] ?? '' );
			$template['full_slug']  = $template['theme'] . '//' . $final_slug;
		} else {
			$template['plugin']        = (string) ( $loc['plugin'] ?? '' );
			$template['plugin_active'] = (bool) ( $loc['plugin_active'] ?? false );
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Updated template "%s".', 'zoltiq-agents' ), $final_slug ),
			'template' => $template,
			'warnings' => $warnings,
		);
	}

	private function migrate( array $loc, string $migrate_to, bool $delete_source, array $input ): array {
		$slug       = (string) ( $loc['slug'] ?? '' );
		$src        = (string) ( $loc['source'] ?? '' );
		$theme_hint = sanitize_key( $input['theme'] ?? '' );
		$warnings   = array();

		$content     = '';
		$source_post = null;
		$source_path = '';
		if ( 'db' === $src ) {
			$source_post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( ! $source_post ) {
				return array(
					'success' => false,
					'message' => __( 'Source DB template not found.', 'zoltiq-agents' ),
				);
			}
			$content = (string) $source_post->post_content;
		} else {
			$source_path = (string) ( $loc['path'] ?? '' );
			$read        = Template_File::read_file( $source_path );
			if ( is_wp_error( $read ) ) {
				return array(
					'success' => false,
					'message' => $read->get_error_message(),
				);
			}
			$content = $read;
		}

		if ( array_key_exists( 'content', $input ) ) {
			$content = (string) $input['content'];
		}

		if ( ! Template_Db::valid_content( $content ) ) {
			return array(
				'success' => false,
				'message' => __( 'Content cannot be empty.', 'zoltiq-agents' ),
			);
		}

		if ( 'db' === $migrate_to ) {
			$theme    = '' !== $theme_hint ? $theme_hint : (string) get_stylesheet();
			$existing = Template_Db::find_by_slug( $slug, $theme );
			if ( $existing ) {
				return array(
					'success' => false,
					'message' => __( 'A DB template with this slug already exists for this theme. Update it directly instead of migrating.', 'zoltiq-agents' ),
				);
			}

			$result = Template_Db::create(
				array(
					'slug'        => $slug,
					'title'       => (string) ( $input['title'] ?? $slug ),
					'description' => (string) ( $input['description'] ?? '' ),
					'content'     => $content,
					'theme'       => $theme,
				)
			);
			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'message' => $result->get_error_message(),
				);
			}

			$new_post   = get_post( (int) $result );
			$warnings[] = __( 'DB version will override the file copy from now on.', 'zoltiq-agents' );

			if ( $delete_source && 'plugin' !== $src && ! ( 'theme' === $src && 'parent' === ( $loc['theme_type'] ?? '' ) ) ) {
				$del = Template_File::delete_file( $source_path );
				if ( is_wp_error( $del ) ) {
					$warnings[] = $del->get_error_message();
				}
			} elseif ( $delete_source ) {
				$warnings[] = __( 'Skipped deleting source — parent-theme and plugin files are preserved.', 'zoltiq-agents' );
			}

			return array(
				'success'  => true,
				'message'  => sprintf( __( 'Migrated "%s" from file to database.', 'zoltiq-agents' ), $slug ),
				'template' => $new_post ? Template_Db::to_row( $new_post ) : array(),
				'migrated' => true,
				'warnings' => $warnings,
			);
		}

		$child_dir = Template_File::get_child_theme_dir();
		if ( null === $child_dir ) {
			return array(
				'success' => false,
				'message' => __( 'No child theme is active. Cannot migrate to child theme. Create a child theme first.', 'zoltiq-agents' ),
			);
		}

		$templates_dir = Template_File::ensure_templates_dir( $child_dir );
		if ( is_wp_error( $templates_dir ) ) {
			return array(
				'success' => false,
				'message' => $templates_dir->get_error_message(),
			);
		}

		$abs = Template_File::resolve_template_path( $child_dir, $slug );
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

		if ( $delete_source && 'db' === $src && null !== $source_post ) {
			Template_Db::delete( $source_post );
			$warnings[] = __( 'DB record deleted — child-theme file is now the only copy.', 'zoltiq-agents' );
		} elseif ( $delete_source && 'theme' === $src && 'parent' === ( $loc['theme_type'] ?? '' ) ) {
			$warnings[] = __( 'Skipped deleting parent-theme source — parent files are preserved.', 'zoltiq-agents' );
		} elseif ( $delete_source && 'plugin' === $src ) {
			$warnings[] = __( 'Skipped deleting plugin source — plugin files are preserved.', 'zoltiq-agents' );
		} else {
			$warnings[] = __( 'Child-theme copy will now override the original. Delete the source with delete_source=true if you want a single copy.', 'zoltiq-agents' );
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Migrated "%1$s" to child theme at %2$s.', 'zoltiq-agents' ), $slug, $abs ),
			'template' => array(
				'source'     => 'theme',
				'theme_type' => 'child',
				'theme'      => basename( $child_dir ),
				'slug'       => $slug,
				'path'       => $abs,
				'bytes'      => (int) $bytes,
			),
			'migrated' => true,
			'warnings' => $warnings,
		);
	}

	private function collect_fields( array $input ): array {
		$data = array();
		foreach ( array( 'title', 'description', 'content', 'status', 'theme' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$data[ $key ] = $input[ $key ];
			}
		}
		return $data;
	}
}
