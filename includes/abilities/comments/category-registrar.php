<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

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
			'zoltiq-agents-comments',
			array(
				'label'       => __( 'Zoltiq Agents - Comments', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing comments: CRUD, moderation (approve / hold / spam), and meta.', 'zoltiq-agents' ),
			)
		);
	}
}
