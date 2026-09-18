<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Create_Internal_Link_Suggestions extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-internal-link-suggestions',
			'args' => array(
				'label'               => __( 'Create Internal Link Suggestions', 'zoltiq-agents' ),
				'description'         => __( 'Persist one or more internal-link suggestions for a given post via the option-backed suggestion store. Each suggestion carries a target URL + proposed anchor text. Store cap: 500 suggestions total.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'suggestions' => array(
							'type'     => 'array',
							'minItems' => 1,
							'maxItems' => 20,
							'items'    => array(
								'type'                 => 'object',
								'properties'           => array(
									'target_url'  => array( 'type' => 'string' ),
									'anchor_text' => array( 'type' => 'string' ),
									'notes'       => array( 'type' => 'string' ),
								),
								'required'             => array( 'target_url', 'anchor_text' ),
								'additionalProperties' => false,
							),
						),
					),
					'required'             => array( 'post_id', 'suggestions' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'created_ids'    => array( 'type' => 'array' ),
						'rejected_items' => array( 'type' => 'array' ),
						'message'        => array( 'type' => 'string' ),
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
		$post_id     = (int) ( $input['post_id'] ?? 0 );
		$suggestions = (array) ( $input['suggestions'] ?? array() );
		if ( $post_id <= 0 || array() === $suggestions ) {
			return array(
				'success' => false,
				'message' => __( 'post_id and a non-empty suggestions array are required.', 'zoltiq-agents' ),
			);
		}
		if ( ! get_post( $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}

		$site_host = wp_parse_url( (string) home_url( '/' ), PHP_URL_HOST );

		$created  = array();
		$rejected = array();
		foreach ( $suggestions as $idx => $s ) {
			if ( ! is_array( $s ) ) {
				$rejected[] = array(
					'index'  => $idx,
					'reason' => 'not-an-object',
				);
				continue;
			}
			$target_url = esc_url_raw( (string) ( $s['target_url'] ?? '' ) );
			$host       = wp_parse_url( $target_url, PHP_URL_HOST );
			if ( '' === $target_url || null === $host || $host !== $site_host ) {
				$rejected[] = array(
					'index'  => $idx,
					'reason' => 'target-not-same-site',
				);
				continue;
			}
			$id = Suggestion_Store::insert(
				array(
					'post_id'     => $post_id,
					'target_url'  => $target_url,
					'anchor_text' => sanitize_text_field( (string) ( $s['anchor_text'] ?? '' ) ),
					'notes'       => sanitize_text_field( (string) ( $s['notes'] ?? '' ) ),
				)
			);
			if ( $id > 0 ) {
				$created[] = $id;
			} else {
				$rejected[] = array(
					'index'  => $idx,
					'reason' => 'store-cap-reached',
				);
			}
		}

		return array(
			'success'        => array() !== $created,
			'created_ids'    => $created,
			'rejected_items' => $rejected,
			'message'        => sprintf( __( 'Created %1$d suggestion(s); %2$d rejected.', 'zoltiq-agents' ), count( $created ), count( $rejected ) ),
		);
	}
}
