<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Helper;

defined( 'ABSPATH' ) || exit;

class Update_Block_Pattern extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-block-pattern',
			'args' => array(
				'label'               => __( 'Update Block Pattern', 'zoltiq-agents' ),
				'description'         => __( 'Updates a block pattern at its current storage location. Auto-detects where it lives; on multi-location ambiguity returns error_code=multiple_locations with the list. For parent-theme-only patterns, copies the file to the child theme first and edits the copy (the parent file is never touched). Pass new_slug to rename (old slug is removed after the new one is written), or target_source to migrate (delete_original controls cleanup).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'               => array( 'type' => 'string' ),
						'source'             => array(
							'type' => 'string',
							'enum' => array( 'db', 'theme', 'plugin' ),
						),
						'theme_type'         => array(
							'type' => 'string',
							'enum' => array( 'child', 'parent', 'theme' ),
						),
						'plugin_slug'        => array( 'type' => 'string' ),

						'title'              => array( 'type' => 'string' ),
						'content'            => array( 'type' => 'string' ),
						'description'        => array( 'type' => 'string' ),
						'viewport_width'     => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'inserter'           => array(
							'type' => 'string',
							'enum' => array( 'yes', 'no' ),
						),
						'categories'         => array( 'type' => 'string' ),
						'keywords'           => array( 'type' => 'string' ),
						'block_types'        => array( 'type' => 'string' ),
						'post_types'         => array( 'type' => 'string' ),
						'template_types'     => array( 'type' => 'string' ),
						'status'             => array(
							'type' => 'string',
							'enum' => array( 'publish', 'draft', 'private', 'pending' ),
						),
						'sync_status'        => array(
							'type' => 'string',
							'enum' => array( 'synced', 'unsynced' ),
						),

						'new_slug'           => array(
							'type'        => 'string',
							'description' => __( 'Rename the pattern. The old slug is removed at the same source after the new one is written.', 'zoltiq-agents' ),
						),

						'target_source'      => array(
							'type'        => 'string',
							'enum'        => array( 'db', 'theme', 'plugin' ),
							'description' => __( 'Migrate the pattern to a different storage layer. The new copy is written first; delete_original controls whether the source copy is removed afterwards.', 'zoltiq-agents' ),
						),
						'target_theme_slug'  => array( 'type' => 'string' ),
						'target_plugin_slug' => array( 'type' => 'string' ),
						'delete_original'    => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'          => array( 'type' => 'boolean' ),
						'message'          => array( 'type' => 'string' ),
						'error_code'       => array( 'type' => 'string' ),
						'locations'        => array( 'type' => 'array' ),
						'pattern'          => array( 'type' => 'object' ),
						'copied_to_child'  => array( 'type' => 'boolean' ),
						'renamed_from'     => array( 'type' => 'string' ),
						'migrated_from'    => array( 'type' => 'object' ),
						'original_removed' => array( 'type' => 'boolean' ),
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
						'idempotent'  => true,
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

		$slug = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return array(
				'success'    => false,
				'message'    => __( 'slug is required.', 'zoltiq-agents' ),
				'error_code' => 'invalid_slug',
			);
		}
		if ( array_key_exists( 'content', $input ) && ! Pattern_Helper::is_valid_content( (string) $input['content'] ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'content may not be empty.', 'zoltiq-agents' ),
				'error_code' => 'empty_content',
			);
		}

		$source      = sanitize_text_field( $input['source'] ?? '' );
		$theme_type  = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );

		$locations = Pattern_Detector::locate( $slug );

		if ( '' === $theme_type && 'theme' === $source ) {
			$child = array_values( array_filter( $locations, static fn( $l ) => ( $l['source'] ?? '' ) === 'theme' && ( $l['theme_type'] ?? '' ) === 'child' ) );
			if ( ! empty( $child ) ) {
				$theme_type = 'child';
			}
		}

		$selected = Pattern_Detector::select( $locations, $source, $theme_type, $plugin_slug );

		$copied_to_child = false;
		if ( is_wp_error( $selected ) ) {
			$code = $selected->get_error_code();
			$data = $selected->get_error_data();

			if ( 'multiple_locations' === $code ) {
				return array(
					'success'    => false,
					'message'    => $selected->get_error_message(),
					'error_code' => $code,
					'locations'  => $data['locations'] ?? $locations,
				);
			}
			if ( 'not_found' === $code || 'not_found_at_source' === $code ) {
				return array(
					'success'    => false,
					'message'    => $selected->get_error_message(),
					'error_code' => $code,
					'locations'  => $locations,
				);
			}
			return array(
				'success'    => false,
				'message'    => $selected->get_error_message(),
				'error_code' => $code,
				'locations'  => $locations,
			);
		}

		if ( 'theme' === $selected['source'] && 'parent' === ( $selected['theme_type'] ?? '' ) && 'parent' !== $theme_type ) {
			$copied = $this->copy_parent_to_child( $selected );
			if ( is_wp_error( $copied ) ) {
				return array(
					'success'    => false,
					'message'    => $copied->get_error_message(),
					'error_code' => $copied->get_error_code(),
				);
			}
			$selected        = $copied;
			$copied_to_child = true;
		}

		$edit_result = $this->edit_at_source( $selected, $input );
		if ( ! $edit_result['success'] ) {
			return $edit_result;
		}

		$renamed_from = '';
		if ( ! empty( $input['new_slug'] ) ) {
			$new_slug = sanitize_title( (string) $input['new_slug'] );
			if ( '' === $new_slug || ! Pattern_Helper::is_valid_bare_slug( $new_slug ) ) {
				return array(
					'success'    => false,
					'message'    => __( 'new_slug is invalid.', 'zoltiq-agents' ),
					'error_code' => 'invalid_new_slug',
				);
			}
			if ( $new_slug !== $slug ) {
				$rename = $this->rename_at_source( $selected, $new_slug, $input );
				if ( ! $rename['success'] ) {
					return $rename;
				}
				$selected     = $rename['pattern_location'];
				$renamed_from = $slug;
			}
		}

		$migrated_from    = null;
		$original_removed = false;
		if ( ! empty( $input['target_source'] ) ) {
			$target_source = sanitize_text_field( (string) $input['target_source'] );
			if ( $target_source !== $selected['source'] ) {
				$migrate = $this->migrate( $selected, $target_source, $input );
				if ( ! $migrate['success'] ) {
					return $migrate;
				}
				$migrated_from    = array(
					'source'     => $selected['source'],
					'theme_type' => $selected['theme_type'] ?? null,
					'theme'      => $selected['theme'] ?? null,
					'plugin'     => $selected['plugin'] ?? null,
					'path'       => $selected['path'] ?? null,
					'post_id'    => $selected['post_id'] ?? null,
				);
				$original_removed = ! empty( $migrate['original_removed'] );
				$selected         = $migrate['pattern_location'];
			}
		}

		return array(
			'success'          => true,
			'message'          => __( 'Pattern updated.', 'zoltiq-agents' ),
			'pattern'          => $selected,
			'copied_to_child'  => $copied_to_child,
			'renamed_from'     => $renamed_from,
			'migrated_from'    => $migrated_from,
			'original_removed' => $original_removed,
		);
	}

	private function copy_parent_to_child( array $parent_location ) {
		$child_dir = Pattern_Helper::get_child_theme_dir();
		if ( null === $child_dir ) {
			return new \WP_Error( 'no_child_theme', __( 'No child theme is active. Create a child theme before editing this parent-theme pattern, or pass theme_type=parent to edit the parent directly.', 'zoltiq-agents' ) );
		}

		$source_file = (string) $parent_location['path'];
		$dest_dir    = $child_dir . '/patterns';
		if ( ! is_dir( $dest_dir ) && ! wp_mkdir_p( $dest_dir ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create /patterns directory in the child theme.', 'zoltiq-agents' ) );
		}

		$dest_file = $dest_dir . '/' . basename( $source_file );
		if ( ! copy( $source_file, $dest_file ) ) {
			return new \WP_Error( 'copy_failed', __( 'Could not copy the parent-theme pattern into the child theme.', 'zoltiq-agents' ) );
		}

		return array(
			'source'     => 'theme',
			'theme_type' => 'child',
			'theme'      => basename( $child_dir ),
			'path'       => $dest_file,
			'slug'       => $parent_location['slug'],
			'writable'   => is_writable( $dest_file ),
		);
	}

	private function edit_at_source( array $location, array $input ): array {
		if ( 'db' === $location['source'] ) {
			$post = get_post( (int) $location['post_id'] );
			if ( ! $post ) {
				return array(
					'success'    => false,
					'message'    => __( 'Pattern post not found.', 'zoltiq-agents' ),
					'error_code' => 'not_found',
				);
			}
			$result = Pattern_Db::update( $post, $input );
			if ( is_wp_error( $result ) ) {
				return array(
					'success'    => false,
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				);
			}
			return array( 'success' => true );
		}

		$abs = (string) $location['path'];
		if ( ! is_writable( $abs ) ) {
			return array(
				'success'    => false,
				'message'    => sprintf(
					/* translators: %s: file path */
					__( 'Pattern file is not writable (%s).', 'zoltiq-agents' ),
					$abs
				),
				'error_code' => 'file_not_writable',
			);
		}

		$existing = file_get_contents( $abs );
		if ( false === $existing ) {
			return array(
				'success'    => false,
				'message'    => __( 'Could not read existing pattern file.', 'zoltiq-agents' ),
				'error_code' => 'read_failed',
			);
		}
		$parsed  = Pattern_Helper::parse_file( $existing );
		$headers = $this->merge_headers( $parsed['headers'], $input );
		$body    = array_key_exists( 'content', $input ) ? (string) $input['content'] : $parsed['body'];

		$bytes = file_put_contents( $abs, Pattern_Helper::build_file( $headers, $body ) );
		if ( false === $bytes ) {
			return array(
				'success'    => false,
				'message'    => __( 'Could not write pattern file.', 'zoltiq-agents' ),
				'error_code' => 'file_not_writable',
			);
		}
		return array( 'success' => true );
	}

	private function rename_at_source( array $location, string $new_slug, array $input ): array {
		$existing_at_target = Pattern_Detector::locate( $new_slug );
		foreach ( $existing_at_target as $other ) {
			if ( $this->same_source_bucket( $other, $location ) ) {
				return array(
					'success'    => false,
					'message'    => __( 'A pattern with new_slug already exists at the target source.', 'zoltiq-agents' ),
					'error_code' => 'new_slug_conflict',
				);
			}
		}

		if ( 'db' === $location['source'] ) {
			$post = get_post( (int) $location['post_id'] );
			if ( ! $post ) {
				return array(
					'success'    => false,
					'message'    => __( 'Pattern post not found.', 'zoltiq-agents' ),
					'error_code' => 'not_found',
				);
			}
			$result = Pattern_Db::update( $post, array( 'new_slug' => $new_slug ) );
			if ( is_wp_error( $result ) ) {
				return array(
					'success'    => false,
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				);
			}
			$new_location         = $location;
			$new_location['slug'] = $new_slug;
			return array(
				'success'          => true,
				'pattern_location' => $new_location,
			);
		}

		$container_dir = dirname( dirname( (string) $location['path'] ) );
		$new_path      = $container_dir . '/patterns/' . $new_slug . '.php';

		$existing = file_get_contents( (string) $location['path'] );
		if ( false === $existing ) {
			return array(
				'success'    => false,
				'message'    => __( 'Could not read pattern file for rename.', 'zoltiq-agents' ),
				'error_code' => 'read_failed',
			);
		}
		$parsed          = Pattern_Helper::parse_file( $existing );
		$prefix          = (string) ( $location['theme'] ?? $location['plugin'] ?? 'theme' );
		$headers         = $parsed['headers'];
		$headers['Slug'] = Pattern_Helper::build_full_slug( $prefix, $new_slug );
		$body            = array_key_exists( 'content', $input ) ? (string) $input['content'] : $parsed['body'];

		$bytes = file_put_contents( $new_path, Pattern_Helper::build_file( $headers, $body ) );
		if ( false === $bytes ) {
			return array(
				'success'    => false,
				'message'    => __( 'Could not write renamed pattern file.', 'zoltiq-agents' ),
				'error_code' => 'file_not_writable',
			);
		}
		if ( ! @unlink( (string) $location['path'] ) ) {

			@unlink( $new_path );
			return array(
				'success'    => false,
				'message'    => __( 'Could not delete old pattern file after rename; rolled back.', 'zoltiq-agents' ),
				'error_code' => 'file_not_writable',
			);
		}

		$new_location         = $location;
		$new_location['slug'] = $new_slug;
		$new_location['path'] = $new_path;
		return array(
			'success'          => true,
			'pattern_location' => $new_location,
		);
	}

	private function migrate( array $location, string $target_source, array $input ): array {
	
		$existing_content = '';
		$existing_title   = '';
		$existing_desc    = '';
		$existing_headers = array();

		if ( 'db' === $location['source'] ) {
			$post = get_post( (int) $location['post_id'] );
			if ( ! $post ) {
				return array(
					'success'    => false,
					'message'    => __( 'Pattern post not found for migration.', 'zoltiq-agents' ),
					'error_code' => 'not_found',
				);
			}
			$existing_content = (string) $post->post_content;
			$existing_title   = (string) $post->post_title;
			$existing_desc    = (string) $post->post_excerpt;
		} else {
			$raw = file_get_contents( (string) $location['path'] );
			if ( false === $raw ) {
				return array(
					'success'    => false,
					'message'    => __( 'Could not read pattern file for migration.', 'zoltiq-agents' ),
					'error_code' => 'read_failed',
				);
			}
			$parsed           = Pattern_Helper::parse_file( $raw );
			$existing_headers = $parsed['headers'];
			$existing_content = $parsed['body'];
			$existing_title   = (string) ( $existing_headers['Title'] ?? '' );
			$existing_desc    = (string) ( $existing_headers['Description'] ?? '' );
		}

		$slug    = (string) $location['slug'];
		$title   = array_key_exists( 'title', $input ) ? sanitize_text_field( (string) $input['title'] ) : $existing_title;
		$desc    = array_key_exists( 'description', $input ) ? sanitize_text_field( (string) $input['description'] ) : $existing_desc;
		$content = array_key_exists( 'content', $input ) ? (string) $input['content'] : $existing_content;

		$create_input = array_merge(
			$input,
			array(
				'source'      => $target_source,
				'slug'        => $slug,
				'title'       => $title,
				'description' => $desc,
				'content'     => $content,
			)
		);
		if ( 'theme' === $target_source && ! empty( $input['target_theme_slug'] ) ) {
			$create_input['theme_slug'] = sanitize_text_field( (string) $input['target_theme_slug'] );
		}
		if ( 'plugin' === $target_source && ! empty( $input['target_plugin_slug'] ) ) {
			$create_input['plugin_slug'] = sanitize_key( (string) $input['target_plugin_slug'] );
		}

		$creator = new Create_Block_Pattern();
		$created = $creator->execute( $create_input );
		if ( empty( $created['success'] ) ) {
			return array(
				'success'    => false,
				'message'    => $created['message'] ?? __( 'Migration failed while writing the target copy.', 'zoltiq-agents' ),
				'error_code' => $created['error_code'] ?? 'migrate_failed',
			);
		}

		$original_removed = false;
		if ( ! empty( $input['delete_original'] ) ) {
			$original_removed = $this->remove_at_source( $location );
		}

		return array(
			'success'          => true,
			'pattern_location' => $created['pattern'] ?? array(
				'source' => $target_source,
				'slug'   => $slug,
			),
			'original_removed' => $original_removed,
		);
	}

	private function remove_at_source( array $location ): bool {
		if ( 'db' === $location['source'] ) {
			$post = get_post( (int) $location['post_id'] );
			return $post && Pattern_Db::delete( $post );
		}
		$path = (string) ( $location['path'] ?? '' );
		if ( '' === $path || ! is_file( $path ) ) {
			return false;
		}
		return @unlink( $path );
	}

	private function same_source_bucket( array $a, array $b ): bool {
		if ( ( $a['source'] ?? '' ) !== ( $b['source'] ?? '' ) ) {
			return false;
		}
		if ( 'theme' === ( $a['source'] ?? '' ) ) {
			return ( $a['theme'] ?? '' ) === ( $b['theme'] ?? '' );
		}
		if ( 'plugin' === ( $a['source'] ?? '' ) ) {
			return ( $a['plugin'] ?? '' ) === ( $b['plugin'] ?? '' );
		}
		return true; 
	}


	private function merge_headers( array $existing, array $input ): array {
		$map = Pattern_Helper::input_to_header_map();
		foreach ( $map as $key => $header ) {
			if ( 'slug_full' === $key ) {
				continue;
			}
			if ( array_key_exists( $key, $input ) ) {
				$value               = is_int( $input[ $key ] ) ? (string) $input[ $key ] : sanitize_text_field( (string) $input[ $key ] );
				$existing[ $header ] = $value;
			}
		}
		return $existing;
	}
}
