<?php

namespace Zoltiq\Agents\Includes\Abilities\Database;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Run_Db_Select_Query extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/run-db-select-query',
			'args' => array(
				'label'               => __( 'Run SELECT Query', 'zoltiq-agents' ),
				'description'         => __( 'Executes a read-only SQL query (SELECT, SHOW, DESCRIBE, EXPLAIN). Pass the query in "sql" (or the alias "query"). Write statements are rejected. Results are capped by the limit parameter.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-database',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'sql'   => array(
							'type'        => 'string',
							'description' => __( 'SQL query to execute. Must start with SELECT, SHOW, DESCRIBE, DESC, or EXPLAIN.', 'zoltiq-agents' ),
						),
						'query' => array(
							'type'        => 'string',
							'description' => __( 'Alias for "sql". If both are provided, "sql" wins.', 'zoltiq-agents' ),
						),
						'limit' => array(
							'type'        => 'integer',
							'default'     => 1000,
							'minimum'     => 1,
							'maximum'     => 10000,
							'description' => __( 'Maximum rows to return (1–10000, default 1000). Appended as LIMIT if the query does not already contain one.', 'zoltiq-agents' ),
						),
					),
					'anyOf'                => array(
						array( 'required' => array( 'sql' ) ),
						array( 'required' => array( 'query' ) ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'rows'      => array( 'type' => 'array' ),
						'row_count' => array( 'type' => 'integer' ),
						'truncated' => array( 'type' => 'boolean' ),
						'message'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'rows', 'row_count', 'truncated' ),
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

		$sql   = isset( $input['sql'] ) && '' !== trim( $input['sql'] )
			? trim( $input['sql'] )
			: trim( (string) ( $input['query'] ?? '' ) );
		$limit = isset( $input['limit'] ) ? min( (int) $input['limit'], 10000 ) : 1000;

		if ( '' === $sql ) {
			return array(
				'success'   => false,
				'rows'      => array(),
				'row_count' => 0,
				'truncated' => false,
				'message'   => __( 'sql (or its alias "query") is required.', 'zoltiq-agents' ),
			);
		}

		$stripped = preg_replace( '/\/\*.*?\*\/|--[^\n]*|#[^\n]*/s', '', $sql );
		preg_match( '/^\s*(\w+)/i', $stripped, $m );
		$first_keyword = strtoupper( $m[1] ?? '' );
		$allowed_verbs = array( 'SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN' );

		if ( ! in_array( $first_keyword, $allowed_verbs, true ) ) {
			return array(
				'success'   => false,
				'rows'      => array(),
				'row_count' => 0,
				'truncated' => false,
				'message'   => sprintf(
					__( 'Only %s queries are permitted.', 'zoltiq-agents' ),
					implode( ', ', $allowed_verbs )
				),
			);
		}

		if ( $limit > 0 && ! preg_match( '/\bLIMIT\b/i', $sql ) ) {
			$sql = rtrim( $sql, '; ' ) . ' LIMIT ' . $limit;
		}
		
		$rows = $wpdb->get_results( $sql, ARRAY_A );
	
		if ( null === $rows ) {
			return array(
				'success'   => false,
				'rows'      => array(),
				'row_count' => 0,
				'truncated' => false,
				'message'   => $wpdb->last_error ?: __( 'Query returned null.', 'zoltiq-agents' ),
			);
		}

		$row_count = count( $rows );

		return array(
			'success'   => true,
			'rows'      => $rows,
			'row_count' => $row_count,
			'truncated' => $row_count === $limit,
		);
	}
}
