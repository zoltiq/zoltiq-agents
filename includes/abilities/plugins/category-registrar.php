<?php

namespace Zoltiq\Agents\Includes\Abilities\Plugins;

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
			'zoltiq-agents-plugins',
			array(
				'label'       => __( 'Zoltq Agents - Plugin Management', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing WordPress plugins: install, activate, deactivate, list, and check for updates.', 'zoltiq-agents' ),
			)
		);
	}
}
