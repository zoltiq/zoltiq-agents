<?php

namespace Zoltiq\Agents\Includes\Abilities\Database;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Extract_Db_Schema extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/extract-db-schema',
			'args' => array(
				'label'               => __( 'Extract Database Schema', 'zoltiq-agents' ),
				'description'         => __( 'Returns the full schema for every table in the database: columns, indexes, and CREATE TABLE SQL.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-database',
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
						'tables'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'       => array( 'type' => 'string' ),
									'columns'    => array( 'type' => 'array' ),
									'indexes'    => array( 'type' => 'array' ),
									'create_sql' => array( 'type' => 'string' ),
								),
							),
						),
					),
					'required'             => array( 'success', 'tables' ),
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

		$table_names = $wpdb->get_col( 'SHOW TABLES' );

		if ( empty( $table_names ) ) {
			return array(
				'success' => true,
				'tables'  => array(),
			);
		}

		$tables = array();

		foreach ( $table_names as $name ) {
			if ( strpos( $name, '`' ) !== false ) {
				continue;
			}

			$escaped = '`' . esc_sql( $name ) . '`';

			$columns    = $wpdb->get_results( "DESCRIBE {$escaped}", ARRAY_A );
			$indexes    = $wpdb->get_results( "SHOW INDEX FROM {$escaped}", ARRAY_A );
			$create_row = $wpdb->get_results( "SHOW CREATE TABLE {$escaped}", ARRAY_N );
	
			$create_sql = isset( $create_row[0][1] ) ? $create_row[0][1] : '';

			$columns_out = array();
			foreach ( (array) $columns as $col ) {
				$columns_out[] = array(
					'name'    => $col['Field'],
					'type'    => $col['Type'],
					'null'    => $col['Null'],
					'key'     => $col['Key'],
					'default' => $col['Default'],
					'extra'   => $col['Extra'],
				);
			}

			$indexes_out = array();
			foreach ( (array) $indexes as $idx ) {
				$indexes_out[] = array(
					'name'   => $idx['Key_name'],
					'column' => $idx['Column_name'],
					'unique' => '0' === $idx['Non_unique'],
					'seq'    => (int) $idx['Seq_in_index'],
				);
			}

			$tables[] = array(
				'name'       => $name,
				'columns'    => $columns_out,
				'indexes'    => $indexes_out,
				'create_sql' => $create_sql,
			);
		}

		return array(
			'success' => true,
			'tables'  => $tables,
		);
	}
}
