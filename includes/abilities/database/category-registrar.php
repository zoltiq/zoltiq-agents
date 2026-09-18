<?php

namespace Zoltiq\Agents\Includes\Abilities\Database;

defined( 'ABSPATH' ) || exit;

final class Category_Registrar {

	protected static $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register(): void {
		wp_register_ability_category(
			'zoltiq-agents-database',
			array(
				'label'       => __( 'Zoltiq Agents - Database', 'zoltiq-agents' ),
				'description' => __( 'Abilities for inspecting and operating on the WordPress database: schema, queries, stats, and maintenance.', 'zoltiq-agents' ),
			)
		);
	}
}
