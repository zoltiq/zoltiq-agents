<?php

namespace Zoltiq\Agents\Includes\Abilities\AdminMenu;

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
			'zoltiq-agents-admin-menu',
			array(
				'label'       => __( 'Zoltiq Agents - Admin Menu', 'zoltiq-agents' ),
				'description' => __( 'Abilities for introspecting the WordPress admin menu: pages, submenus, settings, and current-screen context.', 'zoltiq-agents' ),
			)
		);
	}
}
