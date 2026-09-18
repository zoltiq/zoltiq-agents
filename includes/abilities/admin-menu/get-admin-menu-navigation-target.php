<?php

namespace Zoltiq\Agents\Includes\Abilities\AdminMenu;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Admin_Menu_Navigation_Target extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-admin-menu-navigation-target',
			'args' => array(
				'label'               => __( 'Get Admin Menu Navigation Target', 'zoltiq-agents' ),
				'description'         => __( 'Resolve a natural-language hint (e.g. "settings > reading", "media library") to the closest matching admin URL by scoring token-overlap against the current admin-menu tree. Returns the top hit with a confidence in [0, 1] plus the top three alternates.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-admin-menu',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'intent' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
					),
					'required'             => array( 'intent' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'resolved_slug'  => array( 'type' => 'string' ),
						'resolved_url'   => array( 'type' => 'string' ),
						'resolved_title' => array( 'type' => 'string' ),
						'confidence'     => array( 'type' => 'number' ),
						'alternates'     => array( 'type' => 'array' ),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}


	public function execute( array $input = array() ): array {
		global $menu, $submenu;

		$intent_raw = sanitize_text_field( (string) ( $input['intent'] ?? '' ) );
		if ( strlen( $intent_raw ) > 500 ) {
			$intent_raw = substr( $intent_raw, 0, 500 );
		}
		$intent = strtolower( trim( $intent_raw ) );
		if ( '' === $intent ) {
			return array(
				'success' => false,
				'message' => __( 'intent is required.', 'zoltiq-agents' ),
			);
		}

		$tokens = array_values( array_filter( preg_split( '/[\s>\-_\/]+/u', $intent ) ?: array(), static fn( $t ): bool => '' !== $t ) );
		if ( array() === $tokens ) {
			return array(
				'success' => false,
				'message' => __( 'intent contains no useful tokens.', 'zoltiq-agents' ),
			);
		}

		$candidates = array();
		if ( is_array( $menu ) ) {
			foreach ( $menu as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$title = strtolower( wp_strip_all_tags( (string) ( $entry[0] ?? '' ) ) );
				$slug  = strtolower( (string) ( $entry[2] ?? '' ) );
				if ( '' === $slug ) {
					continue;
				}
				$candidates[] = array(
					'title' => $title,
					'slug'  => $slug,
					'url'   => menu_page_url( $slug, false ),
				);
				if ( isset( $submenu[ $entry[2] ] ) && is_array( $submenu[ $entry[2] ] ) ) {
					foreach ( $submenu[ $entry[2] ] as $sub_entry ) {
						if ( ! is_array( $sub_entry ) ) {
							continue;
						}
						$sub_title = strtolower( wp_strip_all_tags( (string) ( $sub_entry[0] ?? '' ) ) );
						$sub_slug  = (string) ( $sub_entry[2] ?? '' );
						if ( '' === $sub_slug ) {
							continue;
						}
						$candidates[] = array(
							'title' => $sub_title,
							'slug'  => strtolower( $sub_slug ),
							'url'   => menu_page_url( $sub_slug, false ),
						);
					}
				}
			}
		}

		if ( array() === $candidates ) {
			return array(
				'success' => false,
				'message' => __( 'No admin menu entries available in this request context.', 'zoltiq-agents' ),
			);
		}

		$scored = array();
		foreach ( $candidates as $cand ) {
			$corpus = $cand['title'] . ' ' . $cand['slug'];
			$hits   = 0;
			foreach ( $tokens as $tok ) {
				if ( false !== strpos( $corpus, $tok ) ) {
					++$hits;
				}
			}
			$score    = count( $tokens ) > 0 ? $hits / count( $tokens ) : 0.0;
			$scored[] = array_merge( $cand, array( 'score' => $score ) );
		}

		usort(
			$scored,
			static fn( $a, $b ): int => $b['score'] <=> $a['score']
		);

		$top        = $scored[0];
		$alternates = array_slice( $scored, 1, 3 );

		return array(
			'success'        => true,
			'resolved_slug'  => (string) $top['slug'],
			'resolved_url'   => (string) $top['url'],
			'resolved_title' => (string) $top['title'],
			'confidence'     => (float) $top['score'],
			'alternates'     => array_map(
				static fn( $c ): array => array(
					'title' => (string) $c['title'],
					'slug'  => (string) $c['slug'],
					'url'   => (string) $c['url'],
					'score' => (float) $c['score'],
				),
				$alternates
			),

			'message'        => sprintf( __( 'Resolved "%1$s" to "%2$s".', 'zoltiq-agents' ), $intent, $top['slug'] ),
		);
	}
}
