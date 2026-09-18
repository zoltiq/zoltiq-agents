<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Internal_Link_Policy extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-internal-link-policy',
			'args' => array(
				'label'               => __( 'Get Internal Link Policy', 'zoltiq-agents' ),
				'description'         => __( 'Return the policy that governs internal-link suggestion creation and application. v1 policy is a static ruleset — future specs may make the ruleset editable via an options page.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'policy_version' => array( 'type' => 'string' ),
						'rules'          => array( 'type' => 'array' ),
						'message'        => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		unset( $input );
		return array(
			'success'        => true,
			'policy_version' => '1.0.0',
			'rules'          => array(
				array(
					'rule'        => 'target-must-be-on-site',
					'description' => __( 'Suggestion target URLs must resolve to a same-site URL at apply time.', 'zoltiq-agents' ),
					'enabled'     => true,
				),
				array(
					'rule'        => 'target-must-be-published',
					'description' => __( 'Suggestion target post must be in `publish` status at apply time.', 'zoltiq-agents' ),
					'enabled'     => true,
				),
				array(
					'rule'        => 'max-per-post',
					'description' => __( 'At most 500 suggestions across the whole store (option-backed cap).', 'zoltiq-agents' ),
					'enabled'     => true,
				),
			),
			'message'        => __( 'Internal-link suggestion policy v1.0.0.', 'zoltiq-agents' ),
		);
	}
}
