<?php

namespace Zoltiq\Agents\Includes\Abilities\SiteHealth;

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
			'zoltiq-agents-site-health',
			array(
				'label'       => __( 'Zoltiq Agents - Site Health', 'zoltiq-agents' ),
				'description' => __( 'Abilities for inspecting Site Health: run the direct status checks (good/recommended/critical) and read the full Site Health Info (server, database, WordPress, theme, plugins, media, filesystem, constants).', 'zoltiq-agents' ),
			)
		);
	}
}
