<?php

namespace Zoltiq\Agents\Includes\Abilities\Database;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Db_Rows extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-db-rows',
			'args' => array(
				'label'               => __( 'Update Rows', 'zoltiq-agents' ),
				'description'         => __( 'Updates rows matching the where clause using $wpdb->update() (values are auto-escaped). Requires a non-empty where to prevent accidental full-table updates.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-database',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'table'        => array(
							'type'        => 'string',
							'description' => __( 'Target table name.', 'zoltiq-agents' ),
						),
						'data'         => array(
							'type'        => 'object',
							'description' => __( 'Column → value map of fields to update.', 'zoltiq-agents' ),
						),
						'values'       => array(
							'type'        => 'object',
							'description' => __( 'Alias for "data". If both are provided, "data" wins.', 'zoltiq-agents' ),
						),
						'where'        => array(
							'type'        => 'object',
							'description' => __( 'Column → value conditions (AND-joined). Must be non-empty.', 'zoltiq-agents' ),
						),
						'data_format'  => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => array( '%s', '%d', '%f' ),
							),
							'description' => __( 'Optional format per data column.', 'zoltiq-agents' ),
						),
						'where_format' => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => array( '%s', '%d', '%f' ),
							),
							'description' => __( 'Optional format per where column.', 'zoltiq-agents' ),
						),
					),
					'allOf'                => array(
						array( 'required' => array( 'table' ) ),
						array( 'required' => array( 'where' ) ),
						array(
							'anyOf' => array(
								array( 'required' => array( 'data' ) ),
								array( 'required' => array( 'values' ) ),
							),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'       => array( 'type' => 'boolean' ),
						'rows_affected' => array( 'type' => 'integer' ),
						'message'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'rows_affected' ),
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
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		global $wpdb;

		$table        = sanitize_text_field( $input['table'] ?? '' );
		$data         = ! empty( $input['data'] ) ? $input['data'] : ( $input['values'] ?? array() );
		$where        = $input['where'] ?? array();
		$data_format  = $input['data_format'] ?? null;
		$where_format = $input['where_format'] ?? null;

		if ( '' === $table ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'table is required.', 'zoltiq-agents' ),
			);
		}

		if ( empty( $data ) || ! is_array( $data ) ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'data (or its alias "values") must be a non-empty object.', 'zoltiq-agents' ),
			);
		}

		if ( empty( $where ) || ! is_array( $where ) ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'where must be a non-empty object to prevent full-table updates.', 'zoltiq-agents' ),
			);
		}

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! in_array( $table, (array) $tables, true ) ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'Table not found in the database.', 'zoltiq-agents' ),
			);
		}

		$result = $wpdb->update( $table, $data, $where, $data_format, $where_format );

		if ( false === $result ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => $wpdb->last_error ?: __( 'Update failed.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success'       => true,
			'rows_affected' => (int) $result,
		);
	}
}
