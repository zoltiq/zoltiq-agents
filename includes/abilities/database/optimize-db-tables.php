<?php

namespace Zoltiq\Agents\Includes\Abilities\Database;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Optimize_Db_Tables extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/optimize-db-tables',
			'args' => array(
				'label'               => __( 'Optimize Database Tables', 'zoltiq-agents' ),
				'description'         => __( 'Runs OPTIMIZE TABLE on the specified tables. Defaults to all WordPress-prefixed tables when no tables are provided. Reclaims unused space and defragments data files.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-database',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'tables' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Table names to optimize. Omit to optimize all WordPress-prefixed tables.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'results' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'table'   => array( 'type' => 'string' ),
									'op'      => array( 'type' => 'string' ),
									'status'  => array( 'type' => 'string' ),
									'message' => array( 'type' => 'string' ),
								),
							),
						),
					),
					'required'             => array( 'success', 'results' ),
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
		global $wpdb;

		$requested = isset( $input['tables'] ) && is_array( $input['tables'] ) ? $input['tables'] : array();

		if ( empty( $requested ) ) {
			$requested = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . '%' ) );
		}

		$requested = array_filter(
			(array) $requested,
			static function ( $t ) {
				return is_string( $t ) && '' !== $t && strpos( $t, '`' ) === false;
			}
		);

		$results = array();

		foreach ( $requested as $table ) {
			$escaped = '`' . esc_sql( $table ) . '`';

			$rows = $wpdb->get_results( "OPTIMIZE TABLE {$escaped}", ARRAY_A );
			

			foreach ( (array) $rows as $row ) {
				$results[] = array(
					'table'   => $row['Table'] ?? $table,
					'op'      => $row['Op'] ?? 'optimize',
					'status'  => $row['Msg_type'] ?? '',
					'message' => $row['Msg_text'] ?? '',
				);
			}
		}

		return array(
			'success' => true,
			'results' => $results,
		);
	}
}
