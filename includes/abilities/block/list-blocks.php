<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Info;

defined( 'ABSPATH' ) || exit;

class List_Blocks extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-blocks',
			'args' => array(
				'label'               => __( 'List Blocks', 'zoltiq-agents' ),
				'description'         => __( 'Lists every block registered with WP_Block_Type_Registry. Filter by category, keyword, source, or any combination. Returns name, title, description, category, icon, keywords, and source for each block, sorted by name.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'category' => array(
							'type'        => 'string',
							'enum'        => array_merge( array( '' ), Block_Info::CATEGORIES ),
							'default'     => '',
							'description' => __( 'Restrict to one block category (text, media, design, widgets, theme, embed).', 'zoltiq-agents' ),
						),
						'keyword'  => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Search across name, title, description, and keywords.', 'zoltiq-agents' ),
						),
						'source'   => array(
							'type'        => 'string',
							'enum'        => array_merge( array( '' ), Block_Info::SOURCES ),
							'default'     => '',
							'description' => __( 'Restrict to one block source (core, plugin, theme, custom).', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'blocks'  => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
						'filters' => array( 'type' => 'object' ),
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
		if ( ! Block_Info::registry_available() ) {
			return array(
				'success' => false,
				'message' => __( 'WP_Block_Type_Registry is not available yet. Call this ability after the WordPress init hook has fired.', 'zoltiq-agents' ),
			);
		}

		$category = sanitize_text_field( $input['category'] ?? '' );
		$keyword  = sanitize_text_field( $input['keyword'] ?? '' );
		$source   = sanitize_text_field( $input['source'] ?? '' );

		if ( '' !== $category && ! Block_Info::valid_category( $category ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Invalid category. Allowed: %s.', 'zoltiq-agents' ), implode( ', ', Block_Info::CATEGORIES ) ),
			);
		}
		if ( '' !== $source && ! Block_Info::valid_source( $source ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Invalid source. Allowed: %s.', 'zoltiq-agents' ), implode( ', ', Block_Info::SOURCES ) ),
			);
		}

		$registered = Block_Info::all_blocks();

		if ( empty( $registered ) ) {
			return array(
				'success' => true,
				'blocks'  => array(),
				'total'   => 0,
				'filters' => array(
					'category' => $category,
					'keyword'  => $keyword,
					'source'   => $source,
				),
				'message' => __( 'No blocks are registered. Check that WordPress core blocks have loaded — this usually means the init hook has not fired yet.', 'zoltiq-agents' ),
			);
		}

		$blocks = array();
		foreach ( $registered as $block ) {
			if ( ! $block instanceof \WP_Block_Type ) {
				continue;
			}
			if ( '' !== $category && (string) $block->category !== $category ) {
				continue;
			}
			if ( '' !== $source && Block_Info::classify_source( $block ) !== $source ) {
				continue;
			}
			if ( '' !== $keyword && ! Block_Info::matches_keyword( $block, $keyword ) ) {
				continue;
			}
			$blocks[] = Block_Info::summary( $block );
		}

		usort(
			$blocks,
			static function ( array $a, array $b ): int {
				return strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);

		$message = '';
		if ( empty( $blocks ) ) {
			$message = __( 'No blocks match the requested filters.', 'zoltiq-agents' );
		}

		return array(
			'success' => true,
			'blocks'  => $blocks,
			'total'   => count( $blocks ),
			'filters' => array(
				'category' => $category,
				'keyword'  => $keyword,
				'source'   => $source,
			),
			'message' => $message,
		);
	}
}
