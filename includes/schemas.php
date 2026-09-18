<?php

defined( 'ABSPATH' ) || exit;


/**
 * Schema definitions for plugin settings validation.
 *
 * Each schema describes the structure, types, and constraints
 * for specific configuration sections used in the plugin.
 */
$schema = [];


/**
 * API settings schema.
 *
 * Defines configuration for LLM provider, API key,
 * model selection, and generation parameters.
 */
$schema['api_page'] = [
    'type' => 'object',
    'required' => ['agents'],
    'properties' => [
        'agents' => [
            'type' => 'array',
            'minItems' => 1,
            'items' => [
                'type' => 'object',
	            'required' => ['provider', 'model_choice', 'temperature', 'connected'],
                'properties' => [
					'slug'	   => [	'type' => 'string' ],
					'name'	   => [	'type' => 'string'],
                    'url'	   => [	'type' => 'string', 'format' => 'uri' ],		
					'provider' => [ 'type' => 'string', 'enum' => [ 'openai', 'google', 'anthropic', 'ollama'] ],
                    'model_choice' => [ 'type' => 'string' ],
    	            'temperature'  => [ 'type' => 'number', 'minimum' => 0, 'maximum' => 2 ],	
					'connected'    => [ 'type' => 'boolean' ]
				],
                'additionalProperties' => false,
            ],
        ],
        'embeddings' => [
            'type' => 'object',
            'properties' => [
				'url'      => [ 'type' => 'string', 'format' => 'uri' ],
                'provider' => [ 'type' => 'string' ],
        	    'model_choice' => [ 'type' => 'string' ],
				'connected'    => [ 'type' => 'boolean' ]
            ],
            'additionalProperties' => false
        ]
    ],
    'additionalProperties' => false
];


/**
 * Chatbot behavior and UI schema.
 *
 * Controls chatbot title, visibility, greeting message,
 * and feature toggles.
 */
$schema['chatbot_page'] = [
   'type' => 'object',
   'properties' => [
	    'title' => [
        	'type'      => 'string',
        	'maxLength' => 255,
    	],
		'initial_greeting' => [
        	'type'      => 'string',
        	'maxLength' => 1000,
    	],
    	'start_status' => [
        	'type' => 'string',
	    	'enum' => [ 'open', 'closed'],
    	],
    	'emoji' => [
       		'type' => 'boolean'
       	],
		'show_in_admin' => [
          	'type' => 'boolean'
      	],
    	'enable_raw_tool' => [
        	'type' => 'boolean'
		],
    	'disable_chatbot_pages' => [
        	'type' => 'array',
        	'items' => [
	        	'type' => 'string',
            	'minLength' => 1,
            	'maxLength' => 255,
        	],
    	],
	],
	'required' => ['title', 'start_status', 'emoji', 'initial_greeting', 'show_in_admin', 'enable_raw_tool'],
];


/**
 * Tools configuration schema.
 *
 * Stores the enabled/disabled state of available tools.
 */
$schema['tools_page'] = [
   'type' => 'object',
   'properties' => [
    	'status_tool_names' => [
        	'type' => 'object',
        	'additionalProperties' => [
	 	    	'type' => 'boolean',
        	],
        	'default' => (object) [],
    	],
	],
];


/**
 * Chatbot style and appearance schema.
 *
 * Defines visual customization such as colors, sizes,
 * spacing, and UI layout.
 */
$schema['style_page'] = [
   'type'       => 'object',
   'properties' => [
    	'styles' => [
        	'type'       => 'object',
        	'properties' => [
				'title_color'                   => ['type' => 'string', 'maxLength' => 50],
				'primary_color'                 => ['type' => 'string', 'maxLength' => 50],
				'text_primary_color'            => ['type' => 'string', 'maxLength' => 50],
				'text_secondary_color'          => ['type' => 'string', 'maxLength' => 50],
				'background_color'              => ['type' => 'string', 'maxLength' => 50],
				'background_text_color'         => ['type' => 'string', 'maxLength' => 50],
				'background_input_color'        => ['type' => 'string', 'maxLength' => 50],
				'outline_input_color'           => ['type' => 'string', 'maxLength' => 50],
				'button_color'                  => ['type' => 'string', 'maxLength' => 50],
				'hover_button_color'            => ['type' => 'string', 'maxLength' => 50],
				'cancel_btn_file_color'         => ['type' => 'string', 'maxLength' => 50],
				'animation_dot_color'           => ['type' => 'string', 'maxLength' => 50],
				'header_background_gradient_col'=> ['type' => 'string', 'maxLength' => 255],
				'width_narrow'                  => ['type' => 'string', 'maxLength' => 20],
				'height_narrow'                 => ['type' => 'string', 'maxLength' => 20],
				'right_margin'                  => ['type' => 'string', 'maxLength' => 20],
				'bottom_margin'                 => ['type' => 'string', 'maxLength' => 20],
				'border_radius'                 => ['type' => 'string', 'maxLength' => 20],
				'toggler_right_margin'          => ['type' => 'string', 'maxLength' => 20],
				'toggler_bottom_margin'         => ['type' => 'string', 'maxLength' => 20],
				'toggler_border_radius'         => ['type' => 'string', 'maxLength' => 20],
				'toggler_size'                  => ['type' => 'string', 'maxLength' => 20],
				'zoom_site_icon'                => ['type' => 'string', 'maxLength' => 10],
        	],
        	'required' => [
				'title_color', 
				'primary_color',
				'text_primary_color',
				'text_secondary_color',
				'background_color',
				'background_text_color',
				'background_input_color',
				'outline_input_color',
				'button_color',
				'hover_button_color',
				'cancel_btn_file_color',
				'animation_dot_color',
				'header_background_gradient_col',
				'width_narrow',
				'height_narrow',
				'right_margin',
				'bottom_margin',
				'border_radius',
				'toggler_right_margin',
				'toggler_bottom_margin',
				'toggler_border_radius',
				'toggler_size',
				'zoom_site_icon'
        	],
        	'additionalProperties' => false,
    	],
    	'input_placeholder' => [
        	'type' => 'string',
        	'maxLength' => 255,
    	],
	],
	'required' => ['styles', 'input_placeholder'],
	'additionalProperties' => false,
];

/**
 * Agents configuration schema.
 *
 * Stores the filename, date, select agents.
 */
$schema['agents_items'] = [
	'type'       => 'object',
	'properties' => [
		'items' => [
			'type'     => 'array',
			'required' => true,
			'items' => [
				'type'       => 'object',
				'properties' => [
					'name'      => ['type' => 'string'],
					'id'        => ['type' => 'integer'],
					'filename'  => ['type' => 'string'],
					'timestamp' => ['type' => 'integer'],
				],
				'required' => ['name', 'id', 'filename', 'timestamp'],
			],
		],
		'select_id' => [
			'type'     => 'integer',
			'required' => true,
		],
	],
];

return $schema;