<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Info;

defined( 'ABSPATH' ) || exit;

class Read_Block extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-block',
			'args' => array(
				'label'               => __( 'Read Block', 'zoltiq-agents' ),
				'description'         => __( 'Returns full details for a single registered block. Pass "section" (settings, supports, attributes, example, variations, styles, transforms) to fetch one slice instead of the whole record. Block name must be in namespace/name form (e.g. core/paragraph).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'    => array(
							'type'        => 'string',
							'description' => __( 'Block name (namespace/block-name, e.g. "core/paragraph").', 'zoltiq-agents' ),
						),
						'section' => array(
							'type'        => 'string',
							'enum'        => array_merge( array( '' ), Block_Info::SECTIONS ),
							'default'     => '',
							'description' => __( 'Return only this section.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'  => array( 'type' => 'boolean' ),
						'block'    => array( 'type' => 'object' ),
						'section'  => array( 'type' => 'string' ),
						'data'     => array(),
						'warnings' => array( 'type' => 'array' ),
						'message'  => array( 'type' => 'string' ),
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

		$name = trim( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name ) {
			return array(
				'success' => false,
				'message' => __( 'Block name is required (namespace/block-name format).', 'zoltiq-agents' ),
			);
		}

		$section_input = sanitize_text_field( $input['section'] ?? '' );
		if ( '' !== $section_input && ! Block_Info::valid_section( $section_input ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Invalid section. Allowed: %s.', 'zoltiq-agents' ), implode( ', ', Block_Info::SECTIONS ) ),
			);
		}

		$block = Block_Info::get_block( $name );
		if ( null === $block ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Block "%s" is not registered. Check the spelling, or use block-info-list to find the correct name.', 'zoltiq-agents' ), $name ),
			);
		}

		$warnings = $this->collect_warnings( $block );
		$summary  = Block_Info::summary( $block );

		if ( '' !== $section_input ) {
			$slice = Block_Info::section( $block, $section_input );
			return array(
				'success'  => true,
				'block'    => $summary,
				'section'  => (string) $slice['section'],
				'data'     => $slice['data'],
				'warnings' => $this->section_availability_warning( (string) $slice['section'], (bool) $slice['available'], $warnings ),
				'message'  => $slice['available']
					? ''
					: sprintf( __( 'No %1$s registered for block "%2$s".', 'zoltiq-agents' ), (string) $slice['section'], $name ),
			);
		}

		$full = Block_Info::full( $block );

		$warnings = $this->compose_section_warnings( $full, $warnings );

		return array(
			'success'  => true,
			'block'    => $summary,
			'section'  => '',
			'data'     => $full,
			'warnings' => $warnings,
		);
	}

	private function collect_warnings( \WP_Block_Type $block ): array {
		$warnings = array();

		if ( property_exists( $block, 'deprecated' ) && ! empty( $block->deprecated ) ) {
			$warnings[] = sprintf( __( 'Block "%s" is marked deprecated. Check the block\'s deprecated metadata for replacement guidance.', 'zoltiq-agents' ), (string) $block->name );
		}

		if ( 0 === strpos( (string) $block->name, 'core/' ) ) {
			$source = Block_Info::classify_source( $block );
			if ( 'core' !== $source ) {
				$warnings[] = sprintf( __( 'Block "%1$s" uses the core/* namespace but the currently registered copy was last registered by %2$s. The last registration wins in WordPress.', 'zoltiq-agents' ), (string) $block->name, $source );
			}
		}

		return $warnings;
	}

	private function section_availability_warning( string $section, bool $available, array $warnings ): array {
		if ( $available ) {
			return $warnings;
		}
		$warnings[] = sprintf( __( 'No %s registered for this block.', 'zoltiq-agents' ), $section );
		return $warnings;
	}

	private function compose_section_warnings( array $full, array $warnings ): array {
		if ( null === ( $full['example'] ?? null ) ) {
			$warnings[] = __( 'No example registered for this block.', 'zoltiq-agents' );
		}
		foreach ( array( 'variations', 'styles', 'transforms' ) as $section ) {
			if ( empty( $full[ $section ] ) ) {
				$warnings[] = sprintf( __( 'No %s registered for this block.', 'zoltiq-agents' ), $section );
			}
		}
		return $warnings;
	}
}
