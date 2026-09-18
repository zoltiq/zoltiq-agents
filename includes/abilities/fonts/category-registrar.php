<?php

namespace Zoltiq\Agents\Includes\Abilities\Fonts;

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
			'zoltiq-agents-fonts',
			array(
				'label'       => __( 'Zoltiq Agents - Fonts', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing the WordPress Font Library: list, read, create, and delete font families and font faces.', 'zoltiq-agents' ),
			)
		);
	}
}
