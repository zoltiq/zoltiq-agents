<?php

namespace Zoltiq\Agents\Services;

use Zoltiq\Agents\Interfaces\Llm_Client_Interface;
use Zoltiq\Agents\Interfaces\Embedding_Provider_Interface;
use Zoltiq\Agents\Exceptions\OpenAI_Exception;
use WP_Error;

/**
 * OpenAI API client implementation.
 *
 * This class acts as a concrete adapter for the OpenAI API,
 * implementing the Llm_Client_Interface used across the application.
 *
 * It is responsible for:
 * - Sending HTTP requests to OpenAI endpoints
 * - Handling errors and response parsing
 * - Providing a unified interface for LLM-related operations
 */
class OpenAI_Client implements Llm_Client_Interface, Embedding_Provider_Interface {
   
	/**
     * OpenAI API key.
     *
     * @var string
     */
	private  $api_key;


	/**
     * Model used for generating embeddings.
     *
     * @var string
     */
	private  $model_embeddings;

   
	/**
     * Base URL for OpenAI API.
     *
     * @var string
     */
	private const BASE_URL = 'https://api.openai.com/v1/';

	/**
	 * Endpoint embeddings.
	 */
	private const OPENAI_ENDPOINT_EMBEDDINGS = 'embeddings';

	/**
	 * Endpoint model list.
	 */
	private const OPENAI_ENDPOINT_MODELS = 'models';

   
    /**
     * Constructor.
     *
     * @param array $config
     */
	public function __construct(array $config) {

		if (empty($config['api_key'])) {
			throw new OpenAI_Exception('OpenAI: missing api_key');
		}

		$this->api_key = $config['api_key'];
		$this->model_embeddings = $config['model_embeddings'] ?? null;
	}

   /**
    * Sends an HTTP request to the OpenAI API.
    *
    * Centralized request handler used by all public methods.
    * Handles authentication, request formatting, error handling,
    * and JSON response decoding.
    *
    * @param string       $method   HTTP method (GET, POST, etc.).
    * @param string       $endpoint API endpoint (relative to base URL).
    * @param string|array $body     Optional request body (JSON string).
    *
    * @throws OpenAI_Exception When request fails or API returns an error.
    * @return array Decoded JSON response from OpenAI.
    */
	private function request($method, $endpoint, $body = '') {
		
		$url = self::BASE_URL . $endpoint;

		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		if (!empty($body)) {
			$args['body'] = $body;
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			throw new OpenAI_Exception($response->get_error_message());
		}

		$status = wp_remote_retrieve_response_code($response);
		$raw    = wp_remote_retrieve_body($response);
		$data   = json_decode($raw, true);
	
		if ($status >= 400) {
			$message = $data['error']['message'] ?? 'Unknown OpenAI error';
			throw new OpenAI_Exception($message, $status);
		}

		return $data;
	}

	/**
     * Retrieves available LLM models from OpenAI.
     *
     * Filters only chat/LLM models (e.g. "gpt-*") and excludes
     * non-relevant model types such as embeddings, audio, or image models.
     *
     * @return array<int, array{id: string, name: string}> List of available models.
     */
	public function list_models(): array {
		
		$data = $this->request('GET', self::OPENAI_ENDPOINT_MODELS);

		if (empty($data['data'])) {
		    return [];
		}
	
		$filtered = array_filter( $data['data'], function ($model) {
			$id = $model['id'];

			// Allow only LLM models (whitelist)
			$is_llm = str_starts_with($id, 'gpt-');

			// Exclude non-chat models (blacklist)
			$excluded = str_contains($id, 'embedding') ||
						str_contains($id, 'image') ||
						str_contains($id, 'audio') ||
						str_contains($id, 'vision') ||
						str_contains($id, 'tts') ||
						str_contains($id, 'whisper');

			return $is_llm && !$excluded;
		} );

		$result = array_map( function ($model) {
			return [
				'id'   => $model['id'],
				'name' => $model['id'],
			];
		}, $filtered );

		return array_values($result);
	}

	/**
     * Generates a response using the OpenAI Responses API.
     *
     * This method is the main entry point for sending prompts/messages
     * to the LLM and receiving generated output.
     *
     * @param string $endpoint API endpoint (e.g. 'responses').
     * @param string $body     JSON-encoded request payload.
     *
     * @return array|WP_Error API response or WordPress error object.
     */
	public function generate_response(string $endpoint, string $body): array|WP_Error {
		return $this->request('POST', $endpoint, $body);
	}

	/**
	 * Calls OpenAI embeddings API.
	 *
	 * @param array $inputs Array of strings to embed
	 * @return array|WP_Error embeddings response or WordPress error object
	 */
	public function embeddings(array $inputs): array|WP_Error {
			
		$payload = wp_json_encode( array(
			'model' => $this->model_embeddings,
			'input' => $inputs,
		));

		$data = $this->request('POST', self::OPENAI_ENDPOINT_EMBEDDINGS, $payload);
		
		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return array( 'error' => 'Invalid OpenAI response.' );
		}

		$out = [];
		foreach ( $data['data'] as $item ) {
			if ( isset( $item['embedding'] ) && is_array( $item['embedding'] ) ) {
				$out[] = $item['embedding'];
			} else {
				$out[] = null;
			}
		}

		return array( 'embeddings' => $out );
	}

	public function list_models_embeddings(): array {
		
		$data = $this->request('GET', self::OPENAI_ENDPOINT_MODELS);
		
		$filtered = array_filter( $data['data'], function ($model) {
			$id = $model['id'];

			// Allow only LLM models (whitelist)
			$is_llm = str_starts_with($id, 'text-');

			return $is_llm;
		} );

		$result = array_map( function ($model) {
			return [
				'id'   => $model['id'],
				'name' => $model['id'],
			];
		}, $filtered );

		return array_values($result);

	}
}