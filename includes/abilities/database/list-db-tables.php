<?php

namespace Zoltiq\Agents\Includes\Abilities\Database;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Db_Tables extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-db-tables',
			'args' => array(
				'label'               => __( 'List Database Tables', 'zoltiq-agents' ),
				'description'         => __( 'Lists all tables in the database with engine, approximate row count, and storage size.', 'zoltiq-agents' ),
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
									'name'             => array( 'type' => 'string' ),
									'engine'           => array( 'type' => 'string' ),
									'row_count'        => array( 'type' => 'integer' ),
									'data_size_bytes'  => array( 'type' => 'integer' ),
									'index_size_bytes' => array( 'type' => 'integer' ),
									'total_size_bytes' => array( 'type' => 'integer' ),
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

		$rows = $wpdb->get_results(
			'SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
			 FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE()
			 ORDER BY TABLE_NAME',
			ARRAY_A
		);

		$tables = array();
		foreach ( (array) $rows as $row ) {
			$data_size  = (int) $row['DATA_LENGTH'];
			$index_size = (int) $row['INDEX_LENGTH'];
			$tables[]   = array(
				'name'             => $row['TABLE_NAME'],
				'engine'           => $row['ENGINE'] ?? '',
				'row_count'        => (int) $row['TABLE_ROWS'],
				'data_size_bytes'  => $data_size,
				'index_size_bytes' => $index_size,
				'total_size_bytes' => $data_size + $index_size,
			);
		}

		return array(
			'success' => true,
			'tables'  => $tables,
		);
	}
}
