<?php

namespace Zoltiq\Agents\Services;

use Zoltiq\Agents\Interfaces\Llm_Client_Interface;
use Zoltiq\Agents\Interfaces\Embedding_Provider_Interface;
use Zoltiq\Agents\Exceptions\Gemini_Exception;
use WP_Error;

/**
 * Google Gemini API client implementation.
 *
 * This class acts as a concrete adapter for the Google Generative Language API,
 * implementing the Llm_Client_Interface used across the application.
 *
 * It is responsible for:
 * - Communicating with the Gemini API
 * - Handling authentication (API key in query string)
 * - Parsing responses and handling errors
 * - Providing a unified interface for LLM operations
 */
class Gemini_Client implements Llm_Client_Interface, Embedding_Provider_Interface {
   
	/**
     * Google API key.
     *
     * @var string
     */
	private $api_key;

    /**
     * Model used for generating embeddings.
     *
     * @var string
     */
	private  $model_embeddings;
   
	/**
     * Base URL for Google Generative Language API.
     *
     * @var string
     */
    private const BASE_URL = 'https://generativelanguage.googleapis.com/';
	
	/**
	 * Constructor.
	 *
	 * @param string $api_key Google API key.
	 */
	public function __construct(array $config) {

        if (empty($config['api_key'])) {
			throw new Gemini_Exception('OpenAI: missing api_key');
		}

		$this->api_key = $config['api_key'];
        $this->model_embeddings = $config['model_embeddings'] ?? null;
	}
   
	/**
     * Sends an HTTP request to the Gemini API.
     *
     * Centralized request handler responsible for:
     * - Adding API key to query parameters
     * - Sending HTTP requests
     * - Handling errors and decoding JSON responses
     *
     * @param string       $method   HTTP method (GET, POST, etc.).
     * @param string       $endpoint API endpoint (relative to base URL).
     * @param string|array $body     Optional request body (JSON string or array).
     *
     * @throws Gemini_Exception When request fails or API returns an error.
     * @return array Decoded JSON response.
     */
	private function request($method, $endpoint, $body = []) {
		
		// API key is passed as a query parameter in Google APIs
		$url = add_query_arg('key', $this->api_key, self::BASE_URL . $endpoint);

		$args = [
			'method'  => $method,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => 30,
		];

		if (!empty($body)) {
			$args['body'] = $body;
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			throw new Gemini_Exception('Connection failed: ' . $response->get_error_message());
		}

		$status = wp_remote_retrieve_response_code($response);
		$raw    = wp_remote_retrieve_body($response);
		$data   = json_decode($raw, true);

		if ($status >= 400) {
			$message = $data['error']['message'] ?? 'Gemini API error';
			throw new Gemini_Exception($message, $status);
		}

		return $data;
	}

	/**
     * Retrieves available Gemini models.
     *
     * Filters only models that support content generation (generateContent),
     * ensuring compatibility with chat/LLM use cases.
     *
     * @return array<int, array{id: string, name: string}> List of supported models.
     */
	public function list_models(): array {
		$data = $this->request('GET', 'v1beta/models');
			
		if (empty($data['models'])) {
		    return [];
		}

		// Filter models that support content generation
		$filtered = array_filter($data['models'] ?? [], function ($model) {
            $name = $model['name'];
			
			// Allow only LLM models (whitelist)
			$is_llm = in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true) &&
                      (str_contains($name, 'gemini-') ||
                      str_contains($name, 'gemma-'));
			
            $excluded = str_contains($name, 'robotics') ||
                        str_contains($name, 'banana') ||
                        str_contains($name, 'image'); 

			return $is_llm && !$excluded;
		});

		// Map to unified format
		$result = array_map(function ($model) {
			return [
				'id'   => $model['name'],
				'name' => $model['displayName'],
			];
		}, $filtered);

		return array_values($result);
	}

	/**
     * Generates a response using the Gemini API.
     *
     * This method is the main entry point for sending prompts/messages
     * to the Gemini model and receiving generated output.
     *
     * @param string $endpoint API endpoint (e.g. 'v1/models/...:generateContent').
     * @param string $body     JSON-encoded request payload.
     *
     * @return array|WP_Error API response or WordPress error object.
     */
	public function generate_response(string $endpoint, string $body): array|WP_Error {
		return $this->request('POST', $endpoint, $body);
	}

	/**
     * Calls Gemini embeddings API using batch processing.
     *
     * @param array $inputs Array of strings to embed
     * @return array|WP_Error embeddings response or WordPress error object
     */
    public function embeddings(array $inputs): array|WP_Error {
            
        $endpoint = 'v1beta/models/' . $this->model_embeddings . ':batchEmbedContents';

        $requests = [];
        foreach ($inputs as $text) {
            $requests[] = [
                'model' => 'models/' . $this->model_embeddings,
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ],
                'outputDimensionality' => 1536
            ];
        }

        $payload = wp_json_encode([
            'requests' => $requests
        ]);

        try {
            $data = $this->request('POST', $endpoint, $payload);
        } catch (Gemini_Exception $e) {
            return new WP_Error('gemini_error', $e->getMessage());
        }

        if ( ! isset( $data['embeddings'] ) || ! is_array( $data['embeddings'] ) ) {
            return new WP_Error('gemini_error', 'Invalid Gemini response structure.');
        }

        $out = [];
        foreach ( $data['embeddings'] as $item ) {
            // W Gemini wektor znajduje się w kluczu 'values'
            if ( isset( $item['values'] ) && is_array( $item['values'] ) ) {
                $out[] = $item['values'];
            } else {
                $out[] = null;
            }
        }

	    return array( 'embeddings' => $out );
    }


    public function list_models_embeddings(): array {
		
		$data = $this->request('GET', 'v1beta/models');

        if (empty($data['models'])) {
		    return [];
		}
        
        
        // Filter models that support embedContent
		$filtered = array_filter($data['models'] ?? [], function ($model) {
			return in_array('embedContent', $model['supportedGenerationMethods'] ?? []);
		});

		// Map to unified format
		$result = array_map(function ($model) {
			return [
				'id'   => $model['name'],
				'name' => $model['displayName'],
			];
		}, $filtered);

		return array_values($result);

	}    
}