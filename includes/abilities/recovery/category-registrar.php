<?php

namespace Zoltiq\Agents\Includes\Abilities\Recovery;

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
			'zoltiq-agents-recovery',
			array(
				'label'       => __( 'Zoltq Agents — Recovery Mode', 'zoltiq-agents' ),
				'description' => __( 'Abilities for operating the site around WordPress Recovery Mode: detect if recovery is active, list paused (fatally-erroring) plugins and themes, clear paused entries so WP retries loading them, get the admin-clickable exit URL, and filter recent fatal errors from debug.log.', 'zoltiq-agents' ),
			)
		);
	}
}
