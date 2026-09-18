<?php

namespace Zoltiq\Agents\Includes\Abilities\Options;

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
			'zoltiq-agents-options',
			array(
				'label'       => __( 'Zoltiq Agents - Options', 'zoltiq-agents' ),
				'description' => __( 'Abilities for reading, writing, and searching the wp_options table.', 'zoltiq-agents' ),
			)
		);
	}
}
