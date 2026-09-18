<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Multilang_Helpers;

defined( 'ABSPATH' ) || exit;

class Link_Post_Translation extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/link-post-translation',
			'args' => array(
				'label'               => __( 'Link Post Translations', 'zoltiq-agents' ),
				'description'         => __( 'Group two or more posts as translations of each other. Pass a map of language code → post ID. Polylang uses pll_save_post_translations(); WPML links each post to the same trid.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'translations' => array(
							'type'        => 'object',
							'description' => __( 'Map of language code → post ID, e.g. { "en": 5, "fr": 9 }.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'translations' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'      => array( 'type' => 'boolean' ),
						'driver'       => array( 'type' => 'string' ),
						'translations' => array( 'type' => 'object' ),
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
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$raw = $input['translations'] ?? array();
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return array(
				'success' => false,
				'message' => __( 'translations must be a non-empty language→ID map.', 'zoltiq-agents' ),
			);
		}

		$clean = array();
		foreach ( $raw as $lang => $id ) {
			$slug = sanitize_key( (string) $lang );
			$pid  = (int) $id;
			if ( '' === $slug || $pid <= 0 || ! get_post( $pid ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Invalid entry "%1$s" → %2$d.', 'zoltiq-agents' ), $lang, $pid ),
				);
			}
			$clean[ $slug ] = $pid;
		}

		$result = Multilang_Helpers::link_translations( $clean );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success'      => true,
			'driver'       => Multilang_Helpers::detect(),
			'translations' => $clean,
			'message'      => __( 'Translations linked.', 'zoltiq-agents' ),
		);
	}
}
