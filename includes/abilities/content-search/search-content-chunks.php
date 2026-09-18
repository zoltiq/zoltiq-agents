<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Search_Content_Chunks extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/search-content-chunks',
			'args' => array(
				'label'               => __( 'Search Content Chunks', 'zoltiq-agents' ),
				'description'         => __( 'Split search-matching post content into paragraph-length chunks and return the ones that contain the query substring. Fallback implementation — no persistent chunk table. Backed by the same WP_Query `s=` search as content-search-items.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'query'    => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 10,
						),
					),
					'required'             => array( 'query' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'chunks'  => array( 'type' => 'array' ),
						'message' => array( 'type' => 'string' ),
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
		$query    = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		$per_page = max( 1, min( 50, (int) ( $input['per_page'] ?? 10 ) ) );
		if ( '' === $query ) {
			return array(
				'success' => false,
				'message' => __( 'query is required.', 'zoltiq-agents' ),
			);
		}

		$wp_query = new \WP_Query(
			array(
				's'              => $query,
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
			)
		);

		$chunks         = array();
		$needle         = strtolower( $query );
		$per_post       = 3;   
		$max_chunk_len  = 500; 
		foreach ( $wp_query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( ! current_user_can( 'read_post', (int) $post->ID ) ) {
				continue;
			}
			$plain      = wp_strip_all_tags( (string) $post->post_content );
			$paragraphs = preg_split( '/\r?\n{2,}/', $plain ) ?: array();
			$hits       = 0;
			foreach ( $paragraphs as $idx => $para ) {
				if ( false === stripos( $para, $needle ) ) {
					continue;
				}
				$text = trim( $para );
				if ( strlen( $text ) > $max_chunk_len ) {
					$text = rtrim( substr( $text, 0, $max_chunk_len ) ) . '...';
				}
				$chunks[] = array(
					'post_id'     => (int) $post->ID,
					'chunk_index' => (int) $idx,
					'text'        => $text,
					'score'       => 1.0,
				);
				++$hits;
				if ( $hits >= $per_post ) {
					break;
				}
			}
		}

		return array(
			'success' => true,
			'chunks'  => $chunks,
			'message' => sprintf( __( 'Returned %d matching chunk(s).', 'zoltiq-agents' ), count( $chunks ) ),
		);
	}
}
