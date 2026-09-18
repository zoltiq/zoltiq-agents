<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Embedding_Indexer;
use Zoltiq\Agents\Services\Llm_Provider_Factory;

defined( 'ABSPATH' ) || exit;

class Documents_Search extends Ability_Definition {

protected function ability(): array {
		return array(
			'name' => 'zoltiq/documents-search',
			'args' => array(
				'label'               => __( 'Search data', 'zoltiq-agents' ),
				'description'         => __( "Always use this when a user's question concerns specific information (business descriptions, terms and conditions, FAQs, procedures) or when other sources have returned no results or low confidence.", 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'query'      => array(
							'type'        => 'string',
                            'description' => 'A question or a conversation snippet to search for'
						)
		    		),
					'required'             => array( 'query' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'content' => array( 'type' => 'string' )
					),
					'required'             => array( 'content' ),
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


    public function execute(array $args) {
        $query = $args['query'];
		$res = $this->search_by_query( $query ); 
		return array( 'content' => $res);
    }

    /**
	 * Search posts by query using embeddings similarity.
	 *
	 * @param string $query Search string
	 * @return string|array Human-readable list of relevant excerpts or error
	 */
	private function search_by_query( $query ) {
    	     
    	$provider = Llm_Provider_Factory::make_embedding_provider();
		$emb_resp = $provider->embeddings( array( $query ) );
		
    	if ( isset( $emb_resp['error'] ) ) {
        	return array( 'error' => 'Embedding error: ' . $emb_resp['error'] );
    	}
    	if ( empty( $emb_resp['embeddings'][0] ) || ! is_array( $emb_resp['embeddings'][0] ) ) {
        	return array( 'error' => 'No embedding returned from model.' );
    	}
    	$query_embedding = $emb_resp['embeddings'][0];

    	// Prepare JSON representation (used for VEC_FromText)
    	$json = wp_json_encode( $query_embedding );
    	if ( false === $json ) {
        	return array( 'error' => 'Failed to json_encode embedding.' );
    	}

		global $wpdb;

 		$chat_embedding = new Embedding_Indexer();
        $table = $wpdb->prefix . $chat_embedding::DB_TABLE_EMBEDDINGS;
    	$top_k = 2; 

		$status = 'publish';
    	$sql = $wpdb->prepare(
			"SELECT t.object_id AS object_id, t.chunk_text AS chunk_text, 
			VEC_DISTANCE_COSINE(t.embedding, VEC_FromText(%s)) AS distance
			FROM {$table} t
			WHERE t.post_status = %s
			ORDER BY distance ASC
			LIMIT %d",
			$json,
			$status,
			$top_k
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( false === $rows ) {
			return array( 'error' => 'DB query failed: ' . $wpdb->last_error );
		}
    
    	$lines = [];
		$i = 1;
		foreach ($rows as $item) {
			$text = isset($item['chunk_text']) ? $item['chunk_text'] : '';
			$text = trim($text);
			$lines[] = "{$i}. {$text}";
			$i++;
		}

		return implode(PHP_EOL, $lines);
	}




}