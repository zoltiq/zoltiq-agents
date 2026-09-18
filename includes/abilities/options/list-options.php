<?php

namespace Zoltiq\Agents\Includes\Abilities\Options;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Options extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-options',
			'args' => array(
				'label'               => __( 'List Options', 'zoltiq-agents' ),
				'description'         => __( 'List wp_options rows. Defaults to names + autoload only; pass include_values=true to embed truncated option values.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-options',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'autoload_only'  => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'page'           => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'       => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 500,
							'default' => 100,
						),
						'include_values' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'options' => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
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
		global $wpdb;

		$per_page = min( 500, max( 1, (int) ( $input['per_page'] ?? 100 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$columns = ! empty( $input['include_values'] )
			? 'option_id, option_name, autoload, LEFT(option_value, 1024) AS option_value, LENGTH(option_value) AS value_bytes'
			: 'option_id, option_name, autoload, LENGTH(option_value) AS value_bytes';

		if ( ! empty( $input['autoload_only'] ) ) {
			$total = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on','auto-off')"
			);
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$columns} FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on','auto-off') ORDER BY option_id ASC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" );
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$columns} FROM {$wpdb->options} ORDER BY option_id ASC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}

		return array(
			'success' => true,
			'options' => is_array( $rows ) ? $rows : array(),
			'total'   => $total,
		);
	}
}
