<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Find_Internal_Links extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/find-internal-links',
			'args' => array(
				'label'               => __( 'Find Internal Links', 'zoltiq-agents' ),
				'description'         => __( 'Parse a post\'s rendered content for <a href> tags whose target resolves to a same-site URL. Returns each match with anchor text + resolved target post id (via url_to_postid()) when available.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'links'   => array( 'type' => 'array' ),
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
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid post_id is required.', 'zoltiq-agents' ),
			);
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to read this post.', 'zoltiq-agents' ),
			);
		}

		$site_host = wp_parse_url( (string) home_url( '/' ), PHP_URL_HOST );

		$content = (string) $post->post_content;

		$links       = array();
		$max_anchor  = 200; 
		if ( preg_match_all( '/<a\s+[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$href = esc_url_raw( (string) $m[2] );
				$text = wp_strip_all_tags( (string) $m[3] );
				if ( strlen( $text ) > $max_anchor ) {
					$text = rtrim( substr( $text, 0, $max_anchor ) ) . '...';
				}
				$host = wp_parse_url( $href, PHP_URL_HOST );
				if ( '' === $href || null === $host || $host !== $site_host ) {
					continue;
				}
				$target_id = url_to_postid( $href );
				$links[]   = array(
					'target_url' => $href,
					'target_id'  => (int) $target_id,
					'anchor'     => sanitize_text_field( $text ),
				);
			}
		}

		return array(
			'success' => true,
			'links'   => $links,
			'message' => sprintf( __( 'Found %1$d internal link(s) in post #%2$d.', 'zoltiq-agents' ), count( $links ), $post_id ),
		);
	}
}
