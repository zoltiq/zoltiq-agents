<?php

namespace Zoltiq\Agents\Includes\Abilities\Cache;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Flush_Rewrite_Rules extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/flush-rewrite-rules',
			'args' => array(
				'label'               => __( 'Flush Rewrite Rules', 'zoltiq-agents' ),
				'description'         => __( 'Flushes WordPress rewrite rules via flush_rewrite_rules(). Use hard=true (default) to also regenerate the .htaccess file, or hard=false for an in-memory-only rebuild.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cache',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array( 'hard' => true ),
					'properties'           => array(
						'hard' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'true regenerates .htaccess (hard flush); false rebuilds only the in-memory rules (soft flush).', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'hard'    => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'hard', 'message' ),
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
		$hard = isset( $input['hard'] ) ? (bool) $input['hard'] : true;

		flush_rewrite_rules( $hard );

		return array(
			'success' => true,
			'hard'    => $hard,
			'message' => $hard
				? __( 'Rewrite rules flushed (hard — .htaccess regenerated).', 'zoltiq-agents' )
				: __( 'Rewrite rules flushed (soft — in-memory only).', 'zoltiq-agents' ),
		);
	}
}
