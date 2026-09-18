<?php

namespace Zoltiq\Agents\Services;

use Zoltiq\Agents\Interfaces\Llm_Client_Interface;
use Zoltiq\Agents\Interfaces\Embedding_Provider_Interface;
use Zoltiq\Agents\Exceptions\Ollama_Exception;
use WP_Error;

class Ollama_Client implements Llm_Client_Interface, Embedding_Provider_Interface {

    /**
     * Base URL for Google Generative Language API.
     *
     * @var string
     */
	private $base_url;

	/**
     * Model used for generating embeddings.
     *
     * @var string
     */
	private  $model_embeddings;

	private const DEFAULT_URL_OLLAMA = 'http://localhost:11434';

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
		$this->base_url = ($config['url'] ?? self::DEFAULT_URL_OLLAMA) . '/v1/';
		$this->model_embeddings = $config['model_embeddings'] ?? null;
	}

	private function request(string $method, string $endpoint, $body = '') {

		$url = $this->base_url . $endpoint;

        $args = [
			'method'  => $method,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => 60,
		];

		if (!empty($body)) {
			$args['body'] = $body;
		}

		$response = wp_remote_request($url, $args);
 
        if (is_wp_error($response)) {
			throw new Ollama_Exception('Connection failed: ' . $response->get_error_message());
		}

        $status = wp_remote_retrieve_response_code($response);
      	$raw    = wp_remote_retrieve_body($response);
		$data   = json_decode($raw, true);
  
        if ($status >= 400) {
			$message = $data['error'] ?? 'Error Ollama API';
			throw new Ollama_Exception($message, $status);
		}

		return $data;
	}

    public function generate_response(string $endpoint, string $body): array|WP_Error {
        return $this->request('POST', $endpoint, $body);
    }

    public function list_models(): array {

        $res = $this->request('GET', self::OPENAI_ENDPOINT_MODELS);
        $models =  $res['data'];

        $result = array_map( function ($model) {
			return [
				'id'   => $model['id'],
				'name' => $model['id'],
			];
		}, $models );

        return $result;
    }

    public function embeddings(array $inputs): array|WP_Error {

        $payload = wp_json_encode( array(
			'model' => $this->model_embeddings,
			'input' => $inputs,
		));

        $data = $this->request('POST', self::OPENAI_ENDPOINT_EMBEDDINGS, $payload);

        if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return new WP_Error('ollama_embedding_error', 'Invalid response');
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
		return $this->list_models();
	}    


}    