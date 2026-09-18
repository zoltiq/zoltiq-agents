<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

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
			'zoltiq-agents-settings',
			array(
				'label'       => __( 'Zoltq Agents - Settings', 'zoltiq-agents' ),
				'description' => __( 'Abilities for reading and updating WordPress Settings: Permalinks, Site Title, Tagline, and Site Icon.', 'zoltiq-agents' ),
			)
		);
	}
}
