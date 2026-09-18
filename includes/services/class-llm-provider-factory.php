<?php

namespace Zoltiq\Agents\Services;

use Zoltiq\Agents\Interfaces\Llm_Client_Interface;
use Zoltiq\Agents\Interfaces\Embedding_Provider_Interface;

/**
 * Factory responsible for creating LLM client instances.
 *
 * Uses a provider registry (mapping) instead of conditional logic,
 * making it easy to extend with new providers without modifying core logic.
 */
class Llm_Provider_Factory {

	/**
	 * Option name where plugin settings are stored.
	 *
	 * @var string
	 */
	private const OPTION_SETTINGS = 'zoltiq_agents_options';

	/**
	 * Option name where the API key is stored.
	 *
	 * @var string
	 */
	private const OPTION_API_KEY = 'zoltiq_agents_api_key';

	/**
	 * Provider registry mapping provider keys to client classes.
	 *
	 * @var array<string, class-string<Llm_Client_Interface>>
	 */
	private static array $providers = [
		'openai'    => OpenAI_Client::class,
		'google'    => Gemini_Client::class,
		'anthropic' => Anthropic_Client::class,
		'ollama'    => Ollama_Client::class
	];

	/**
	 * Creates an LLM client instance based on plugin configuration.
	 * @param string $provider
	 * @return Llm_Client_Interface|null
	 */
	public static function make( $provider = null ): ?Llm_Client_Interface {
		
		if (!$provider) {
			return null;
		}
		
		$class = self::$providers[$provider] ?? null;

		if (!$class) {
			return null;
		}
		
		$config = self::get_provider_config( $provider );
		$config['api_key']  = self::get_api_key( $provider );


		try {
			return new $class($config);
		} catch (\Throwable $e) {
			return null;
		}


	}

	/**
	 * Returns the currently selected provider from settings.
	 * @param string $provider
	 * @return string|null
	 */
	private static function get_selected_provider( $provider ): ?string {
		$options = get_option(self::OPTION_SETTINGS, []);

		foreach ($options['api_page']['agents'] as $agent) {
			if (($agent['provider'] ?? null) === $provider) {
				return $agent['provider'];
			}
    	}

    	return null;
	}

	/**
	 * Returns the currently selected provider from settings.
	 *
	 * @return array|null
	 */
	private static function get_provider_embed_config(): ?array {
		$options = get_option(self::OPTION_SETTINGS, []);
		return $options['api_page']['embeddings'] ?? null;
	}

	/**
	 * Returns the currently selected config a provider.
	 * @param string $provider
	 * @return array|null
	 */
	private static function get_provider_config( $provider ): array {
		$options = get_option(self::OPTION_SETTINGS, []);
    	$agents = $options['api_page']['agents'] ?? [];

		foreach ($agents as $agent) {
			if (($agent['provider'] ?? null) === $provider) {
				return $agent;
			}
		}

    	return [];
	}

	/**
	 * Returns API key from WordPress options.
	 *
	 * @param string $provider
	 * @return string|null
 	 */
	private static function get_api_key( $provider ): ?string {
		$api_keys = get_option(self::OPTION_API_KEY);
		return !empty($api_keys[$provider]) ? $api_keys[$provider] : null;
	}

	/**
	 * Registers a new LLM provider dynamically.
	 *
	 * Allows extending the system without modifying this class.
	 *
	 * @param string $key
	 * @param class-string<Llm_Client_Interface> $class
	 * @return void
	 */
	public static function register_provider(string $key, string $class): void {
		if (is_subclass_of($class, Llm_Client_Interface::class)) {
			self::$providers[$key] = $class;
		}
	}

	/**
	 * Returns all registered providers.
	 *
	 * @return array<string, class-string<Llm_Client_Interface>>
	 */
	public static function get_providers(): array {
		return self::$providers;
	}

	public static function make_embedding_provider(): ?Embedding_Provider_Interface {

		$config = self::get_provider_embed_config();
		if (!$config) {
			return null;
		}

		$provider = $config['provider'];
		$class = self::$providers[$provider] ?? null;

		if (!$class) {
			return null;
		}

		$model = $config['model_choice'] ?? null;

		$config['api_key'] = self::get_api_key( $provider );
		$config['model_embeddings'] = $model;

		try {
			$client = new $class($config);

			if ($client instanceof Embedding_Provider_Interface) {
				return $client;
			}

			return null;

		} catch (\Throwable $e) {
			return null;
		}
	}


}