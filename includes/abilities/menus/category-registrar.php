<?php

namespace Zoltiq\Agents\Includes\Abilities\Menus;

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
			'zoltiq-agents-menus',
			array(
				'label'       => __( 'Zolt Agents - Menus', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing nav menus and menu items via the core REST endpoints.', 'zoltiq-agents' ),
			)
		);
	}
}
