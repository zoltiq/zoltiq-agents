<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

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
			'zoltiq-agents-cron',
			array(
				'label'       => __( 'Zoltiq Agents - Cron', 'zoltiq-agents' ),
				'description' => __( 'Abilities for inspecting and managing WP-Cron: list/get/check scheduled events, list and define schedules, run hooks on demand, and delete events.', 'zoltiq-agents' ),
			)
		);
	}
}
