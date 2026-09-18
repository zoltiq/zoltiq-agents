<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Audit_Internal_Links extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/audit-internal-links',
			'args' => array(
				'label'               => __( 'Audit Internal Links', 'zoltiq-agents' ),
				'description'         => __( 'Scan up to N published posts for internal <a href> URLs and report broken ones. Broken = same-site URL that resolves to no post_id, or resolves to a post that is not in `publish` status. Makes no outbound HTTP requests; external-link health is out of scope.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'broken'  => array( 'type' => 'array' ),
						'ok'      => array( 'type' => 'integer' ),
						'checked' => array( 'type' => 'integer' ),
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
		$per_page  = max( 1, min( 100, (int) ( $input['per_page'] ?? 20 ) ) );
		$site_host = wp_parse_url( (string) home_url( '/' ), PHP_URL_HOST );

		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$broken  = array();
		$ok      = 0;
		$checked = 0;
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( ! current_user_can( 'read_post', (int) $post->ID ) ) {
				continue;
			}
			$content = (string) $post->post_content;
			if ( ! preg_match_all( '/<a\s+[^>]*href=(["\'])(.*?)\1[^>]*>/is', $content, $matches, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $matches as $m ) {
				$href = esc_url_raw( (string) $m[2] );
				$host = wp_parse_url( $href, PHP_URL_HOST );
				if ( '' === $href || null === $host || $host !== $site_host ) {
					continue;
				}
				++$checked;
				$target_id = url_to_postid( $href );
				if ( 0 === $target_id ) {
					$broken[] = array(
						'post_id'    => (int) $post->ID,
						'target_url' => $href,
						'reason'     => 'unresolved',
					);
					continue;
				}
				$target = get_post( $target_id );
				if ( ! $target instanceof \WP_Post || 'publish' !== $target->post_status ) {
					$broken[] = array(
						'post_id'    => (int) $post->ID,
						'target_url' => $href,
						'reason'     => 'not-published',
					);
					continue;
				}
				++$ok;
			}
		}

		return array(
			'success' => true,
			'broken'  => $broken,
			'ok'      => $ok,
			'checked' => $checked,
			'message' => sprintf( __( 'Audited %2$d internal link(s); %1$d broken.', 'zoltiq-agents' ), count( $broken ), $checked ),
		);
	}
}
