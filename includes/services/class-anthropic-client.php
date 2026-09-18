<?php

namespace Zoltiq\Agents\Services;

use Zoltiq\Agents\Interfaces\Llm_Client_Interface;
use Zoltiq\Agents\Exceptions\Anthropic_Exception;
use WP_Error;


/**
 * Anthropic (Claude) API client implementation.
 *
 * This class acts as a concrete adapter for the Anthropic API,
 * implementing the Llm_Client_Interface used across the application.
 *
 * It is responsible for:
 * - Communicating with the Anthropic API
 * - Handling authentication via headers
 * - Parsing responses and handling errors
 * - Providing a unified interface for LLM operations
 */
class Anthropic_Client implements Llm_Client_Interface {


	/**
     * Anthropic API key.
     *
     * @var string
     */
	private $api_key;
	
	
	/**
     * Base URL for Anthropic API.
     *
     * @var string
     */
	private $base_url = 'https://api.anthropic.com/';

   
	/**
     * Constructor.
     *
     * @param string $api_key Anthropic API key.
     */
	public function __construct($api_key) {
    	$this->api_key = $api_key;
	}


	/**
     * Sends an HTTP request to the Anthropic API.
     *
     * Centralized request handler responsible for:
     * - Setting required headers (API key, versioning)
     * - Sending HTTP requests
     * - Handling errors and decoding JSON responses
     *
     * @param string       $method   HTTP method (GET, POST, etc.).
     * @param string       $endpoint API endpoint (relative to base URL).
     * @param string|array $body     Optional request body (JSON string or array).
     *
     * @throws Anthropic_Exception When request fails or API returns an error.
     * @return array Decoded JSON response.
     */
	private function request($method, $endpoint, $body = []) {
 
		$url = $this->base_url . $endpoint;

		$args = [
			'method'  => $method,
			'headers' => [
				'X-Api-Key'         => $this->api_key,
				'Anthropic-Version' => '2023-06-01',
			'Content-Type'      => 'application/json',
		],
			'timeout' => 30,
		];

		if (!empty($body)) {
			$args['body'] = $body;
		}

		$response = wp_remote_request($url, $args);
		
		if (is_wp_error($response)) {
			throw new Anthropic_Exception('Connection failed: ' . $response->get_error_message());
		}

		$status = wp_remote_retrieve_response_code($response);
		$raw    = wp_remote_retrieve_body($response);
		$data   = json_decode($raw, true);
		
		if ($status >= 400) {
			$message = $data['error']['message'] ?? 'Anthropic API error';
			throw new Anthropic_Exception($message, $status);
		}

		return $data;
	}


	/**
     * Retrieves available Anthropic models.
     *
     * Filters only conversational Claude models (e.g. "claude-*"),
     * ensuring compatibility with chat-based use cases.
     *
     * @return array<int, array{id: string, name: string}> List of supported models.
     */
	public function list_models(): array {
		$data = $this->request('GET', 'v1/models');

		if (empty($data['data'])) {
			return [];
		}

		// Filter only Claude conversational models
		$filtered = array_filter($data['data'], function ($model) {
			return (
				isset($model['type']) && $model['type'] === 'model' &&
				isset($model['id']) && str_starts_with($model['id'], 'claude-')
			);
		});

		// Map to unified format
		$result = array_map(function ($model) {
			return [
				'id'   => $model['id'],
				'name' => $model['display_name'] ?? $model['id'],
			];
		}, $filtered);

		return array_values($result);
	}


	/**
     * Generates a response using the Anthropic API.
     *
     * This method is the main entry point for sending prompts/messages
     * to the Claude model and receiving generated output.
     *
     * @param string $endpoint API endpoint (e.g. 'v1/messages').
     * @param string $body     JSON-encoded request payload.
     *
     * @throws Anthropic_Exception When API request fails.
     * @return array|WP_Error API response or WordPress error object.
     */
	public function generate_response(string $endpoint, string $body): array|WP_Error {
    	return $this->request('POST', $endpoint, $body);
	}
}