<?php

namespace Zoltiq\Agents\Includes\Abilities\Integrations;

use Zoltiq\Agents\Includes\Abilities\Library\Integrations\Zoltiq_Integration_Ability_Base;

defined( 'ABSPATH' ) || exit;

class ACF extends Zoltiq_Integration_Ability_Base {

	public const TAB_GROUP = 'acf';

	protected function slug(): string {
		return self::TAB_GROUP;
	}

	protected function label(): string {
		return __( 'Advanced Custom Fields (AI)', 'zoltiq-agents' );
	}

	protected function is_plugin_active(): bool {
		return defined( 'ACF_VERSION' ) && function_exists( 'acf_get_setting' );
	}

	protected function enable_filter(): void {
		add_filter( 'acf/settings/enable_acf_ai', '__return_true' );
	}

	protected function abilities(): array {
		return array(
			array(
				'slug'        => 'acf/field-groups',
				'label'       => __( 'Field Groups', 'zoltiq-agents' ),
				'description' => __(
					'Create and manage ACF field groups (list, get, create, update, delete) so AI clients can extend post/user/taxonomy edit screens.',
					'zoltiq-agents'
				),
			),
			array(
				'slug'        => 'acf/post-types',
				'label'       => __( 'Post Types', 'zoltiq-agents' ),
				'description' => __(
					'Create and manage custom post types via ACF (list, get, create, update, delete).',
					'zoltiq-agents'
				),
			),
			array(
				'slug'        => 'acf/taxonomies',
				'label'       => __( 'Taxonomies', 'zoltiq-agents' ),
				'description' => __(
					'Create and manage custom taxonomies via ACF (list, get, create, update, delete).',
					'zoltiq-agents'
				),
			),
		);
	}
}
