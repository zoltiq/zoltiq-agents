<?php

namespace Zoltiq\Agents\Includes\Abilities\Database;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Db_Rows extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-db-rows',
			'args' => array(
				'label'               => __( 'Delete Rows', 'zoltiq-agents' ),
				'description'         => __( 'Deletes rows matching the where clause using $wpdb->delete() (values are auto-escaped). Requires a non-empty where to prevent accidental full-table deletion.', 'zoltiq-agents' ),
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
						'where'        => array(
							'type'        => 'object',
							'description' => __( 'Column → value conditions (AND-joined). Must be non-empty.', 'zoltiq-agents' ),
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
					'required'             => array( 'table', 'where' ),
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
		$where        = $input['where'] ?? array();
		$where_format = $input['where_format'] ?? null;

		if ( '' === $table ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'table is required.', 'zoltiq-agents' ),
			);
		}

		if ( empty( $where ) || ! is_array( $where ) ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'where must be a non-empty object to prevent full-table deletion.', 'zoltiq-agents' ),
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

		$result = $wpdb->delete( $table, $where, $where_format );

		if ( false === $result ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => $wpdb->last_error ?: __( 'Delete failed.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success'       => true,
			'rows_affected' => (int) $result,
		);
	}
}
