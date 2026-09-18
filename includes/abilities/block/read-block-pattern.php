<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Pattern\Pattern_Helper;

defined( 'ABSPATH' ) || exit;

class Read_Block_Pattern extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-block-pattern',
			'args' => array(
				'label'               => __( 'Read Block Pattern', 'zoltiq-agents' ),
				'description'         => __( 'Reads a pattern by slug from one of: db (wp_block CPT), theme /patterns folder, or plugin /patterns folder. Omit "source" to auto-detect — if the slug exists in more than one location the call fails with error_code=multiple_locations and the list of locations.', 'zoltiq-agents' ),
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
							'description' => __( 'Bare pattern slug (the post_name for DB; the filename minus .php for files).', 'zoltiq-agents' ),
						),
						'source'      => array(
							'type' => 'string',
							'enum' => array( 'db', 'theme', 'plugin' ),
						),
						'theme_type'  => array(
							'type' => 'string',
							'enum' => array( 'child', 'parent', 'theme' ),
						),
						'plugin_slug' => array(
							'type' => 'string',
						),
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
						'locations'  => array( 'type' => 'array' ),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$slug        = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$source      = sanitize_text_field( $input['source'] ?? '' );
		$theme_type  = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );

		if ( '' === $slug ) {
			return array(
				'success'    => false,
				'message'    => __( 'slug is required.', 'zoltiq-agents' ),
				'error_code' => 'invalid_slug',
			);
		}

		$locations = Pattern_Detector::locate( $slug );
		$selected  = Pattern_Detector::select( $locations, $source, $theme_type, $plugin_slug );

		if ( is_wp_error( $selected ) ) {
			$data = $selected->get_error_data();
			return array(
				'success'    => false,
				'message'    => $selected->get_error_message(),
				'error_code' => $selected->get_error_code(),
				'locations'  => isset( $data['locations'] ) ? $data['locations'] : $locations,
			);
		}

		if ( 'db' === $selected['source'] ) {
			$post = get_post( (int) $selected['post_id'] );
			if ( ! $post ) {
				return array(
					'success'    => false,
					'message'    => __( 'Pattern post disappeared.', 'zoltiq-agents' ),
					'error_code' => 'not_found',
				);
			}
			return array(
				'success' => true,
				'pattern' => Pattern_Db::to_row( $post ),
			);
		}

		$contents = file_get_contents( $selected['path'] ); 
		if ( false === $contents ) {
			return array(
				'success'    => false,
				'message'    => __( 'Could not read pattern file.', 'zoltiq-agents' ),
				'error_code' => 'read_failed',
			);
		}
		$parsed = Pattern_Helper::parse_file( $contents );

		$pattern = array_merge(
			$selected,
			array(
				'headers' => $parsed['headers'],
				'body'    => $parsed['body'],
			)
		);

		return array(
			'success' => true,
			'pattern' => $pattern,
		);
	}
}
