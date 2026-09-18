<?php

namespace Zoltiq\Agents\Includes\Abilities\Cache;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Flush_Object_Cache extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/flush-object-cache',
			'args' => array(
				'label'               => __( 'Flush Object Cache', 'zoltiq-agents' ),
				'description'         => __( 'Flushes the entire WordPress object cache via wp_cache_flush(). Useful after data changes when stale cached values may be served.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cache',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'message' ),
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
		$ok = wp_cache_flush();
		return array(
			'success' => (bool) $ok,
			'message' => $ok
				? __( 'Object cache flushed.', 'zoltiq-agents' )
				: __( 'wp_cache_flush() returned false; the active object cache may not support flushing.', 'zoltiq-agents' ),
		);
	}
}
