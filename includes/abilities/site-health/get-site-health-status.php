<?php

namespace Zoltiq\Agents\Includes\Abilities\SiteHealth;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use WP_Site_Health;

defined( 'ABSPATH' ) || exit;

class Get_Site_Health_Status extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-site-health-status',
			'args' => array(
				'label'               => __( 'Get Site Health Status', 'zoltiq-agents' ),
				'description'         => __( 'Run the WordPress Site Health direct tests and return the per-test results together with the good / recommended / critical counts shown on Tools → Site Health → Status. Optionally also runs async tests via their direct fallbacks.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-site-health',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'include_async' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Also run async tests via their direct fallback callbacks (loopback, dotorg communication, background updates, https status, page cache when available).', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'totals'  => array(
							'type'       => 'object',
							'properties' => array(
								'good'        => array( 'type' => 'integer' ),
								'recommended' => array( 'type' => 'integer' ),
								'critical'    => array( 'type' => 'integer' ),
								'total'       => array( 'type' => 'integer' ),
							),
						),
						'tests'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'test'        => array( 'type' => 'string' ),
									'label'       => array( 'type' => 'string' ),
									'status'      => array( 'type' => 'string' ),
									'badge'       => array( 'type' => 'object' ),
									'description' => array( 'type' => 'string' ),
									'actions'     => array( 'type' => 'string' ),
								),
							),
						),
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
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		$include_async = (bool) ( $input['include_async'] ?? true );
		$site_health   = WP_Site_Health::get_instance();
		$tests         = WP_Site_Health::get_tests();
		$results       = array();

		foreach ( $tests['direct'] as $test_id => $test ) {
			$callback = $this->resolve_direct_callback( $site_health, $test );
			if ( null === $callback ) {
				continue;
			}
			$result = $this->run_test( $callback );
			if ( is_array( $result ) ) {
				$results[] = $this->normalize_result( $test_id, $test, $result );
			}
		}

		if ( $include_async ) {
			foreach ( $tests['async'] as $test_id => $test ) {
				if ( empty( $test['async_direct_test'] ) || ! is_callable( $test['async_direct_test'] ) ) {
					continue;
				}
				$result = $this->run_test( $test['async_direct_test'] );
				if ( is_array( $result ) ) {
					$results[] = $this->normalize_result( $test_id, $test, $result );
				}
			}
		}

		$totals = array(
			'good'        => 0,
			'recommended' => 0,
			'critical'    => 0,
			'total'       => count( $results ),
		);
		foreach ( $results as $result ) {
			$status = $result['status'] ?? '';
			if ( isset( $totals[ $status ] ) ) {
				++$totals[ $status ];
			}
		}

		return array(
			'success' => true,
			'totals'  => $totals,
			'tests'   => $results,
		);
	}

	private function resolve_direct_callback( WP_Site_Health $site_health, array $test ) {
		if ( isset( $test['test'] ) && is_string( $test['test'] ) ) {
			$method = sprintf( 'get_test_%s', $test['test'] );
			if ( method_exists( $site_health, $method ) && is_callable( array( $site_health, $method ) ) ) {
				return array( $site_health, $method );
			}
		}
		if ( isset( $test['test'] ) && is_callable( $test['test'] ) ) {
			return $test['test'];
		}
		return null;
	}

	private function run_test( $callback ) {
		try {
			$result = call_user_func( $callback );
		} catch ( \Throwable $e ) {
			return null;
		}
		return apply_filters( 'site_status_test_result', $result );
	}


	private function normalize_result( string $test_id, array $test, array $result ): array {
		return array(
			'test'        => isset( $result['test'] ) ? (string) $result['test'] : $test_id,
			'label'       => isset( $result['label'] ) ? (string) $result['label'] : (string) ( $test['label'] ?? $test_id ),
			'status'      => isset( $result['status'] ) ? (string) $result['status'] : '',
			'badge'       => isset( $result['badge'] ) && is_array( $result['badge'] ) ? $result['badge'] : array(),
			'description' => isset( $result['description'] ) ? (string) $result['description'] : '',
			'actions'     => isset( $result['actions'] ) ? (string) $result['actions'] : '',
		);
	}
}
