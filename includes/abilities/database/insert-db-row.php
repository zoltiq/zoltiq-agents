<?php

namespace Zoltiq\Agents\Includes\Abilities\Database;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Insert_Db_Row extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/insert-db-row',
			'args' => array(
				'label'               => __( 'Insert Row', 'zoltiq-agents' ),
				'description'         => __( 'Inserts a single row into a database table using $wpdb->insert() (values are auto-escaped). Not idempotent — each call adds a new row.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-database',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'table'  => array(
							'type'        => 'string',
							'description' => __( 'Target table name (must exist in the database).', 'zoltiq-agents' ),
						),
						'data'   => array(
							'type'        => 'object',
							'description' => __( 'Column → value map for the new row.', 'zoltiq-agents' ),
						),
						'values' => array(
							'type'        => 'object',
							'description' => __( 'Alias for "data". If both are provided, "data" wins.', 'zoltiq-agents' ),
						),
						'format' => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => array( '%s', '%d', '%f' ),
							),
							'description' => __( 'Optional format per column (%s string, %d integer, %f float). Defaults to %s for each column.', 'zoltiq-agents' ),
						),
					),
					'allOf'                => array(
						array( 'required' => array( 'table' ) ),
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
						'insert_id'     => array( 'type' => 'integer' ),
						'rows_affected' => array( 'type' => 'integer' ),
						'message'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'insert_id', 'rows_affected' ),
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
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		global $wpdb;

		$table  = sanitize_text_field( $input['table'] ?? '' );
		$data   = ! empty( $input['data'] ) ? $input['data'] : ( $input['values'] ?? array() );
		$format = $input['format'] ?? null;

		if ( '' === $table ) {
			return array(
				'success'       => false,
				'insert_id'     => 0,
				'rows_affected' => 0,
				'message'       => __( 'table is required.', 'zoltiq-agents' ),
			);
		}

		if ( empty( $data ) || ! is_array( $data ) ) {
			return array(
				'success'       => false,
				'insert_id'     => 0,
				'rows_affected' => 0,
				'message'       => __( 'data (or its alias "values") must be a non-empty object.', 'zoltiq-agents' ),
			);
		}

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! in_array( $table, (array) $tables, true ) ) {
			return array(
				'success'       => false,
				'insert_id'     => 0,
				'rows_affected' => 0,
				'message'       => __( 'Table not found in the database.', 'zoltiq-agents' ),
			);
		}

		$result = $wpdb->insert( $table, $data, $format );

		if ( false === $result ) {
			return array(
				'success'       => false,
				'insert_id'     => 0,
				'rows_affected' => 0,
				'message'       => $wpdb->last_error ?: __( 'Insert failed.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success'       => true,
			'insert_id'     => (int) $wpdb->insert_id,
			'rows_affected' => (int) $result,
		);
	}
}
