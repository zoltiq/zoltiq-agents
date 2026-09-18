<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Detector;

defined( 'ABSPATH' ) || exit;

class Delete_Block_Pattern extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-block-pattern',
			'args' => array(
				'label'               => __( 'Delete Block Pattern', 'zoltiq-agents' ),
				'description'         => __( 'Deletes a pattern from one storage location: db, theme /patterns, or plugin /patterns. Auto-detects the source; returns error_code=multiple_locations on ambiguity. For theme deletions, the child theme is preferred unless theme_type=parent is set explicitly.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'        => array( 'type' => 'string' ),
						'source'      => array(
							'type' => 'string',
							'enum' => array( 'db', 'theme', 'plugin' ),
						),
						'theme_type'  => array(
							'type' => 'string',
							'enum' => array( 'child', 'parent', 'theme' ),
						),
						'plugin_slug' => array( 'type' => 'string' ),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'message'    => array( 'type' => 'string' ),
						'error_code' => array( 'type' => 'string' ),
						'deleted'    => array( 'type' => 'object' ),
						'locations'  => array( 'type' => 'array' ),
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
						'destructive' => true,
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

		$source      = sanitize_text_field( $input['source'] ?? '' );
		$theme_type  = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );

		$locations = Pattern_Detector::locate( $slug );

		if ( '' === $theme_type && 'theme' === $source ) {
			$child = array_values(
				array_filter(
					$locations,
					static function ( $l ): bool {
						return ( $l['source'] ?? '' ) === 'theme' && ( $l['theme_type'] ?? '' ) === 'child';
					}
				)
			);
			if ( ! empty( $child ) ) {
				$theme_type = 'child';
			}
		}

		$selected = Pattern_Detector::select( $locations, $source, $theme_type, $plugin_slug );

		if ( is_wp_error( $selected ) ) {
			$code = $selected->get_error_code();
			$data = $selected->get_error_data();
			return array(
				'success'    => false,
				'message'    => $selected->get_error_message(),
				'error_code' => $code,
				'locations'  => $data['locations'] ?? $locations,
			);
		}

		if ( 'db' === $selected['source'] ) {
			$post = get_post( (int) $selected['post_id'] );
			if ( ! $post || ! Pattern_Db::delete( $post ) ) {
				return array(
					'success'    => false,
					'message'    => __( 'Could not delete pattern post.', 'zoltiq-agents' ),
					'error_code' => 'delete_failed',
				);
			}
			return array(
				'success' => true,
				'message' => __( 'Pattern deleted from the database.', 'zoltiq-agents' ),
				'deleted' => $selected,
			);
		}

		$path = (string) $selected['path'];
		if ( ! is_writable( $path ) ) {
			return array(
				'success'    => false,
				'message'    => sprintf(
					__( 'Pattern file is not writable (%s).', 'zoltiq-agents' ),
					$path
				),
				'error_code' => 'file_not_writable',
			);
		}
		if ( ! @unlink( $path ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'Could not delete pattern file.', 'zoltiq-agents' ),
				'error_code' => 'delete_failed',
			);
		}

		$message = ( 'plugin' === $selected['source'] && empty( $selected['plugin_active'] ) )
			? __( 'Pattern file deleted from inactive plugin.', 'zoltiq-agents' )
			: __( 'Pattern file deleted.', 'zoltiq-agents' );

		return array(
			'success' => true,
			'message' => $message,
			'deleted' => $selected,
		);
	}
}
