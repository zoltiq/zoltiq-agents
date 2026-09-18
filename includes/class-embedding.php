<?php

namespace Zoltiq\Agents;

use Zoltiq\Agents\Services\Llm_Provider_Factory;
use WP_Query;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Class Embedding_Indexer
 * 
 * Handles the embedding of WordPress posts using OpenAI embeddings API,
 * storage of embeddings in the database, and retrieval of relevant chunks.
 */
class Embedding_Indexer {

	/**
	 * Database table name for storing doc embeddings.
	 */
	public const DB_TABLE_EMBEDDINGS = 'zoltiq_agents_embed';

	/**
	 * Database table name for tools embeddings.
	 */
	private const DB_TABLE_TOOLS_EMBEDDINGS = 'zoltiq_tools_embed';

	/**
	 * Stored database meta.
	 */
	public const OPTION_DB_VERSION = 'zoltiq_agents_database_version';

	/**
	 * Statuses allowed for WordPress posts.
	 */
	private const POST_STATUSES = [
        'publish',
        'draft',
        'future',
        'pending',
        'private',
    ];

	/**
	 * Main process: split posts into chunks, batch call embeddings, and save each chunk.
	 *
	 * Accepts post IDs in two forms:
	 *  - simple numeric array of post IDs (legacy)
	 *  - associative array [ <post_id> => true|false, ... ] (new behavior)
	 *    false => delete existing embeddings
	 *
	 * @param array $post_ids List of post IDs or associative array
	 * @param int $page Current page for pagination
	 * @param int $per_page Items per page
	 * @param array $opts Optional settings ['words_per_chunk'=>250, 'overlap'=>50, 'batch_size'=>16]
	 * @param array|null $all_posts_type Optional post type to embed all posts
	 * @return array Report with processed, deleted counts, errors, and paginated posts
	 */
	public function embedding_posts( $post_ids, $page, $per_page, $opts = [], $all_posts_type = null ) {

		$db_info = get_option(self::OPTION_DB_VERSION, []);
		$is_db_vector = $db_info['vector'] ?? false;

        if ($is_db_vector === false) {
			return new WP_Error(
				'database_not_supported',
				__('The current database does not support vector search functionality.','zoltiq-chatbot'),
				array( 'status' => 503 )
			);
        }
			
		global $wpdb;

		$table            = $wpdb->prefix . self::DB_TABLE_EMBEDDINGS; // adjust if needed
		$tokens_per_chunk = (int) ( $opts['tokens_per_chunk'] ?? 512 );
		$overlap          = (int) ( $opts['overlap'] ?? 75 );
		$batch_size       = (int) ( $opts['batch_size'] ?? 16 ); // how many chunks per embeddings call

		$report = [ 'processed' => 0, 'deleted' => 0, 'scheduled_updates' => 0, 'errors' => [] ];

		// Normalize input: build two lists: $to_embed (ids) and $to_delete (ids)
		$to_embed  = [];
		$to_delete = [];

		foreach ( $post_ids as $k => $v ) {
			// If keys are numeric (e.g. [1021] => true), treat key as post id and value as flag.
			// Otherwise (e.g. [0] => 1021), treat value as post id and flag = true.
			if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
				$post_id = (int) $k;
				$flag = $v;
			} else {
				$post_id = (int) $v;
				$flag = true;
			}

			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				continue;
			}

			$flag_bool = $this->normalize_flag( $flag );

			if ( $flag_bool ) {
				$to_embed[] = $post_id;
			} else {
				$to_delete[] = $post_id;
			}
		}
		
		$to_delete = array_values( array_unique( $to_delete ) );

		if(!empty($all_posts_type)) {
			$ids = get_posts( array(
				'post_type'      => $all_posts_type,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => self::POST_STATUSES,  
				'no_found_rows'  => true,
			));
			$to_embed = array_values( array_diff( $ids, $to_delete ) );
		}

		$to_embed = array_values( array_unique( $to_embed ) );

		// 0) perform deletions first
		if ( ! empty( $to_delete ) ) {
			foreach ( $to_delete as $del_id ) {
				$del_res = $this->delete_post_embeddings( (int) $del_id );
				if ( $del_res === false ) {
					$report['errors']["delete_{$del_id}"] = 'DB delete failed';
				} else {
					$report['deleted'] += (int) $del_res;
				}
			}
		}

		// 0.5) check existing embeddings table to find posts requiring update
		// Query distinct object_id with their latest embedding_date
		$rows = $wpdb->get_results( "SELECT object_id, MAX(embedding_date_gmt) AS last_embedding FROM {$table} GROUP BY object_id", ARRAY_A );
		if ( is_array( $rows ) && ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$post_id = (int) $r['object_id'];

				// Skip if user requested deletion for this post
				if ( in_array( $post_id, $to_delete, true ) ) {
					continue;
				}
				// Skip if already scheduled to embed
				if ( in_array( $post_id, $to_embed, true ) ) {
					continue;
				}

				$last_embedding = $r['last_embedding'];
				// Missing embedding_date -> schedule for embedding (safety)
				if ( empty( $last_embedding ) ) {
					$to_embed[] = $post_id;
					$report['scheduled_updates']++;
					continue;
				}

				// Get post and compare modification date
				$post = get_post( $post_id );
				if ( ! $post ) {
					// Post does not exist anymore — could optionally delete embeddings, but skip for now
					continue;
				}

				// Prefer GMT to avoid timezone mismatch; fallback to local modified if not present
				$post_mod_str = ! empty( $post->post_modified_gmt ) ? $post->post_modified_gmt : $post->post_modified;
				$post_mod_ts  = strtotime( $post_mod_str );
				$emb_ts       = strtotime( $last_embedding );

				if ( $post_mod_ts === false || $emb_ts === false ) {
					// If any parse error, be conservative and schedule update
					$to_embed[] = $post_id;
					$report['scheduled_updates']++;
					continue;
				}

				// If post modified after the last embedding, schedule update
				if ( $post_mod_ts > $emb_ts ) {
					$to_embed[] = $post_id;
					$report['scheduled_updates']++;
				}
			}
		}

		// If there are no posts to embed, return (we already handled deletions)
		if ( empty( $to_embed ) ) {
			$posts = $this->spl_rest_get_post_list($page, $per_page);	
			return [ 'posts' => $posts, ...$report];
		}

		// 1) Build global list of chunks to embed (mapping to post and chunk_index)
		$all_chunks = []; // each item: [ 'object_id'=>int, 'chunk_index'=>int, 'text'=>string ]
		foreach ( array_unique( $to_embed ) as $post_id ) {
			$post_id = (int) $post_id;
			$post = get_post( $post_id );
			if ( ! $post ) {
				$report['errors']["post_{$post_id}"] = 'Post not found';
				continue;
			}

			[$text, $title] = $this->get_clean_post_text( $post );
			if ( null === $text || trim( $text ) === '' ) {
				$report['errors']["post_{$post_id}"] = 'Empty content';
				continue;
			}


		
			$chunks = $this->split_text_to_chunks( $text, $tokens_per_chunk, $overlap );
			if ( empty( $chunks ) ) {
				$report['errors']["post_{$post_id}"] = 'No chunks produced';
				continue;
			}

			// Tutaj obliczamy total dla tego posta i zapisujemy w każdym elemencie
			$total_chunks_for_post = count( $chunks );
						
			foreach ( $chunks as $i => $chunk_text ) {
				$all_chunks[] = [
					'object_id'   => $post_id,
					'object_type'  => $post->post_type,
					'chunk_index' => (int) $i,
					'text'        => $chunk_text,
					'total_chunks' => $total_chunks_for_post,
				];
			}
		}

		if ( empty( $all_chunks ) ) {
			$posts = $this->spl_rest_get_post_list($page, $per_page);	
			return [ 'posts' => $posts, ...$report];
		}

		// 2) Batch embeddings for chunks
		$chunks_batches = array_chunk( $all_chunks, $batch_size );

		foreach ( $chunks_batches as $batch_index => $batch_chunks ) {
			$inputs = array_map( function( $c ) { return $c['text']; }, $batch_chunks );

			$provider = Llm_Provider_Factory::make_embedding_provider();

			$emb_resp = $provider->embeddings($inputs);

			// KLUCZOWY MOMENT: Sprawdź, czy to nie jest błąd WP_Error
			if ( is_wp_error( $emb_resp ) ) {
				// Tutaj obsłuż błąd, np. zaloguj go i przerwij operację
				error_log( 'Błąd Gemini: ' . $emb_resp->get_error_message() );
				return; // lub throw new Exception, zależnie od logiki
			}

			if ( isset( $emb_resp['error'] ) ) {
				$report['errors']["batch_{$batch_index}"] = $emb_resp['error'];
				continue;
			}
			$embeddings = $emb_resp['embeddings'] ?? null;
			if ( ! is_array( $embeddings ) || count( $embeddings ) !== count( $inputs ) ) {
				$report['errors']["batch_{$batch_index}"] = 'Embeddings response mismatch';
				continue;
			}


			// 3) Persist each chunk embedding
			foreach ( $embeddings as $i => $embedding ) {
				$chunk_meta = $batch_chunks[ $i ];
				
				$object_id    = (int) $chunk_meta['object_id'];
				$object_type  = $chunk_meta['object_type'];
				$chunk_index  = (int) $chunk_meta['chunk_index'];
				$chunk_text   = $chunk_meta['text'];
				$total_chunks = (int) $chunk_meta['total_chunks'];

				$res = $this->save_chunk_embedding( $object_id, $object_type, $chunk_index, $chunk_text, $embedding, $total_chunks );
				if ( $res === false ) {
					$report['errors']["post_{$object_id}_chunk_{$chunk_index}"] = 'DB save failed';
					continue;
				}

				$report['processed']++;
			}
		}

		$posts = $this->spl_rest_get_post_list($page, $per_page);	

		return [ 'posts' => $posts, ...$report];
	}
  
	/**
	 * Normalize various possible boolean-like values to actual bool.
	 *
	 * @param mixed $v Input value
	 * @return bool Normalized boolean
	 */
	private function normalize_flag( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		
		if ( is_int( $v ) || is_float( $v ) ) {
			return (bool) $v;
		}
		$v = strtolower( trim( (string) $v ) );
		
		// treat common "false" strings as false
		if ( in_array( $v, [ '0', 'false', 'no', 'n', 'off', '' ], true ) ) {
			return false;
		}
		return true;
	}
   
	/**
	 * Retrieves the cleaned text of a post (title + content) or null if unavailable/unpublished.
	 *
	 * @param object $post WordPress post object
	 * @return array|null [text, title] or null if empty
	 */
	protected function get_clean_post_text($post)
	{
		$content = $post->post_content ?: $post->post_excerpt;

		$charset = get_bloginfo('charset') ?: 'UTF-8';

		$title = get_the_title($post->ID);

		if ($title === '') {
			if ($post->post_type === 'product_variation' && $post->post_parent) {
				$parent = get_post($post->post_parent);
				$title = sprintf(
					'Variation of: %s (ID %d)',
					$parent ? get_the_title($parent->ID) : 'no-title',
					$post->ID
				);
			} else {
				$title = sprintf('#%d (no-title)', $post->ID);
			}
		}

		$title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, $charset);

		// usuń shortcode poprawnie
		$raw_text = strip_shortcodes($content);

		// zamień <br> i </p> na nowe linie
		$raw_text = preg_replace('/<\s*br\s*\/?>/i', "\n", $raw_text);
		$raw_text = preg_replace('/<\/p>/i', "\n\n", $raw_text);

		// usuń HTML
		$text = wp_strip_all_tags($raw_text, false);

		// Dekoduj encje
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, $charset);

		// normalizacja końców linii
		$text = str_replace(["\r\n", "\r"], "\n", $text);

		// Usuwa białe znaki na początku i końcu całego tekstu:
		$text = preg_replace("/[ \t]+/u", " ", trim($text));

		// Maksymalnie dwie puste linie
		$text = preg_replace("/(\r?\n){3,}/u", "\n\n", $text);

		return [
			$title . "\n\n" . $text,
			$title
		];
	}	

	/**
	 * Split a long text into chunks of N words with overlap.
	 *
	 * @param string $text Input text
	 * @param int $tokens_per_chunk Number of words per chunk
	 * @param int $overlap_words Number of words overlapping between chunks
	 * @return string[] Array of chunk texts (ordered)
	 */
	protected function split_text_to_chunks( $text, $tokens_per_chunk = 512, $overlap_words = 50 ) {
		
		if ( $text === '' ) {
			return [];
		}

		$tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		$total_words = count( $tokens );

		if ( $total_words <= $tokens_per_chunk ) {
			return [ $text ];
		}

		$chunks = [];
		$start = 0;
		while ( $start < $total_words ) {
			$slice = array_slice( $tokens, $start, $tokens_per_chunk );
			$chunks[] = implode( '', $slice );

			$start += ( $tokens_per_chunk - $overlap_words );
			if ( $start < 0 ) { $start = 0; } // safety
		}
		
		return $chunks;
	}
	
	/**
	 * Save one chunk embedding to DB - uses VEC_FromText if available, otherwise JSON fallback.
	 *
	 * @param int $object_id Post ID
	 * @param int $chunk_index Chunk index
	 * @param string $chunk_text Chunk text
	 * @param array $embedding Embedding vector
	 * @param int $total_chunks Total chunks for the post
	 * @return bool|int False on error, otherwise DB query result
	 */
	protected function save_chunk_embedding( int $object_id, string $object_type, int $chunk_index, string $chunk_text, array $embedding, int $total_chunks ) {
		global $wpdb;

		$table = $wpdb->prefix . self::DB_TABLE_EMBEDDINGS;

		$now = current_time( 'mysql', true );
		$json = wp_json_encode( $embedding );
		if ( false === $json ) {
			error_log( '[zoltiq_agents] json_encode failed for embedding' );
			return false;
		}

		$sql = $wpdb->prepare(
			"INSERT INTO {$table} (object_id, object_type, chunk_index, chunk_text, embedding, embedding_date_gmt)
			VALUES (%d, %s, %d, %s, VEC_FromText(%s), %s)
			ON DUPLICATE KEY UPDATE object_type = VALUES(object_type), chunk_text = VALUES(chunk_text), embedding = VALUES(embedding), embedding_date_gmt = VALUES(embedding_date_gmt)",
			$object_id,
			$object_type,
			$chunk_index,
			$chunk_text,
			$json,
			$now
		);

		$res = $wpdb->query( $sql );
		if ( false === $res ) {
			error_log( "[zoltiq_agents] DB write failed (object_id {$object_id} chunk {$chunk_index}): " . $wpdb->last_error );
			return false;
		}

		// Only run cleanup when this is the last chunk to avoid deleting while other chunks still being inserted.
		if ( $chunk_index === ( $total_chunks - 1 ) ) {
			$del_sql = $wpdb->prepare(
				"DELETE FROM {$table} WHERE object_id = %d AND chunk_index >= %d",
				$object_id,
				$total_chunks
			);

			$del_res = $wpdb->query( $del_sql );
			if ( false === $del_res ) {
				// Log but do not fail the overall operation (main insert already succeeded).
				error_log( "[zoltiq_agents] Cleanup delete failed (object_id {$object_id} after index {$total_chunks}): " . $wpdb->last_error );
			}
		}

		return $res;
	}


	/**
	 * Delete all chunk embeddings for a given post.
	 *
	 * @param int $post_id Post ID
	 * @return int|false Number of deleted rows or false on error
	 */
	public function delete_post_embeddings( $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::DB_TABLE_EMBEDDINGS; 
		if ( $post_id <= 0 ) {
			return 0;
		}

		$res = $wpdb->delete( $table, array( 'object_id' => $post_id ), array( '%d' ) );
		return $res;
	}



	/**
	 * Returns a paginated list of posts with basic metadata and embedding info.
	 *
	 * @param int $page Page number
	 * @param int $per_page Items per page
	 * @return array Paginated list of posts with embed info
	 */
	public function spl_rest_get_post_list( $page, $per_page ) {
    	global $wpdb;

    	$types = array('post', 'page');

    	// Add WooCommerce post types, if registered
    	if ( class_exists( 'WooCommerce' ) ) {
        	if ( post_type_exists('product') ) {
            	$types[] = 'product';
        	}
        	if ( post_type_exists('product_variation') ) {
            	$types[] = 'product_variation';
        	}
    	}

		// Post type counts
		$post_type_counts = array();

		foreach ( $types as $type ) {
			$counts = wp_count_posts( $type );
			$total_type = 0;

			foreach ( self::POST_STATUSES as $status ) {
				if ( isset( $counts->$status ) ) {
					$total_type += (int) $counts->$status;
				}
			}
			$post_type_counts[ $type ] = $total_type;
		}


		// Embedded post type counts
		$embedded_post_type_counts = array();

		// Initialize all types with 0
		foreach ( $types as $type ) {
			$embedded_post_type_counts[ $type ] = 0;
		}

		$table = $wpdb->prefix . self::DB_TABLE_EMBEDDINGS;

		// Check if the table exists
		$maybe_table = $wpdb->get_var(
			$wpdb->prepare( "SHOW TABLES LIKE %s", $table )
		);

		if ( $maybe_table === $table ) {

			// Prepare placeholders for post types
			$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

			$sql = $wpdb->prepare(
				"
				SELECT object_type, COUNT(*) AS total
				FROM {$table}
				WHERE chunk_index = 0
				AND object_type IN ({$placeholders})
				GROUP BY object_type
				",
				...$types
			);

			$rows = $wpdb->get_results( $sql );

			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$embedded_post_type_counts[ $row->object_type ] = (int) $row->total;
				}
			}
		}
		
		$args = array(
			'post_type'      => $types,
			'post_status'    => self::POST_STATUSES,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'suppress_filters'=> false,
		);

		$q = new WP_Query($args);

		// Prepare a list of post IDs
		$post_ids = array();
		if ( ! empty( $q->posts ) ) {
			$post_ids = array_map( 'intval', wp_list_pluck( $q->posts, 'ID' ) );
		}

    	// Prepare the embedded map: object_id => embedding_date (ISO8601)
    	$embedded_map = array(); 

    	if ( ! empty( $post_ids ) ) {
        	$table = $wpdb->prefix . self::DB_TABLE_EMBEDDINGS;

        	// Check if the table exists
        	$maybe_table = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        	if ( $maybe_table === $table ) {
            	
				$in_list = implode( ',', $post_ids );

				// Retrieve the latest embedding_date for each object_id
				$sql = "
				SELECT object_id, MAX(embedding_date_gmt) AS embedding_date
				FROM {$table}
				WHERE object_id IN ({$in_list})
				GROUP BY object_id
				";

				$rows = $wpdb->get_results( $sql );
				if ( ! empty( $rows ) ) {
					foreach ( $rows as $r ) {
						$oid = (int) $r->object_id;
						$raw_date = $r->embedding_date;
						if ( $raw_date ) {
							// Try to parse and return ISO8601
							$ts = strtotime( $raw_date );
							if ( $ts !== false ) {
								$iso = date( 'c', $ts ); // ISO 8601
							} else {
								$iso = $raw_date; // Fallback: raw format
							}
							$embedded_map[ $oid ] = $iso;
						} else {
							$embedded_map[ $oid ] = null;
						}
					}
				}
        	}
    	}

    	$items = array();

		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$post = get_post();

				[$text, $title] = $this->get_clean_post_text($post);

				// Short introduction (trimmed to ~20 words)
				$excerpt = wp_trim_words( $text, 20, '…' );

				// Author
				$author_id = $post->post_author;
				$author_name = $author_id ? get_the_author_meta('display_name', $author_id) : null;

				// Published boolean i status
				$published = ($post->post_status === 'publish');

				// Date (ISO8601 + formatted)
				$date_iso = get_post_modified_time( 'c', false, $post );
				
				// Embed i embedding_date (if available)
				$has_embed = array_key_exists( $post->ID, $embedded_map );
				$embedding_date_iso = $has_embed ? (new DateTimeImmutable($embedded_map[ $post->ID ], new DateTimeZone('UTC')))
				->setTimezone( function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC') )
				->format(DATE_ATOM) : null;

				$embedding_date_formatted = $embedding_date_iso ? wp_date( get_option('date_format').' '.get_option('time_format'), ( new DateTimeImmutable($embedding_date_iso) )->getTimestamp() ) : null;

				
				$post_type = get_post_type_object($post->post_type);
				
				$items[] = array(
				'id' => (int) $post->ID,
				'post_type' => array( 
					'value' => $post->post_type,
					'label' => $post_type ? $post_type->labels->singular_name : $post->post_type,
				),
				'title' => $title,
				'excerpt' => $excerpt,
				'author' => array(
					'id' => $author_id ? (int)$author_id : null,
					'display_name' => $author_name,
				),
				'published' => (bool) $published,
				'status' => array(
					'value' => $post->post_status,
					'label' => get_post_status_object($post->post_status)->label,
				),
				'date' => $date_iso,
				'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
				'embed' => (bool) $has_embed,
				'embedding_date' => $embedding_date_iso, // ISO8601 string or null
				'embedding_date_formatted' => $embedding_date_formatted
				);
			}
			wp_reset_postdata();
		}

		// Meta pagination
		$total = (int) $q->found_posts;
		$per_page_effective = $per_page == -1 ? $total : (int) $per_page;
		$pages = $per_page_effective > 0 ? ceil( $total / $per_page_effective ) : 1;

		$response = array(
			'total' => $total,
			'per_page' => $per_page_effective,
			'pages' => (int) $pages,
			'page' => (int) $page,
			'post_counts' => [
				'post_type' => $post_type_counts,
				'embedded_post_type' => $embedded_post_type_counts,
			],
			'items' => $items,
		);

		return $response;
	}


	/**
	 * Hook called on post status transition to update embedding table.
	 *
	 * @param string $new_status New post status
	 * @param string $old_status Previous post status
	 * @param object $post WordPress post object
	 */
	public function status_transition( $new_status, $old_status, $post ) {

		if ( ! is_object( $post ) ) return;
		if ( $new_status === $old_status ) return;
		if ( wp_is_post_revision( $post->ID ) ) return;

		global $wpdb;
		$table = $wpdb->prefix . self::DB_TABLE_EMBEDDINGS;

		static $running = false;
		if ( $running ) return;
		$running = true;

		try {
      		$exists = $wpdb->get_var( $wpdb->prepare(
           		"SELECT id FROM {$table} WHERE object_id = %d",
            	$post->ID
      		));

			if ( is_null( $exists ) ) {
				return;
			}

			$data   = array( 'post_status' => $new_status );
			$where  = array( 'object_id'   => $post->ID );
			$format = array( '%s' );
			$where_format = array( '%d' );
			$wpdb->update( $table, $data, $where, $format, $where_format );
		} finally {
			$running = false;
		}
	}

  
	public static function recreate_embedding_table($count_vector) {
		$count_vector = absint($count_vector);
		
		if ($count_vector < 1 || $count_vector > 4096) {
    		return false;
		}

		$res = self::recreate_embedding_doc_table($count_vector);
		if (! $res ) return $res;

		$res = self::recreate_embedding_tools_table($count_vector);
			
		return $res;
	}


	private static function recreate_embedding_doc_table($count_vector) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::DB_TABLE_EMBEDDINGS;

		// Sprawdzenie czy tabela istnieje
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table_name
			)
		);
		
		if ($table_exists === $table_name) {
			
			// Usunięcie tabeli wraz z indeksem VECTOR
			$drop = $wpdb->query(
				"DROP TABLE {$table_name}"
			);

			if ($drop === false) {
				return false;
			}
		}

		// Utworzenie tabeli z nowym wymiarem embedding
		$charset_collate = $wpdb->get_charset_collate();

		$create_sql = "CREATE TABLE `{$table_name}` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`object_id` BIGINT UNSIGNED NOT NULL,
			`object_type` VARCHAR(50) NOT NULL DEFAULT '',
			`chunk_index` INT NOT NULL DEFAULT 0,
			`chunk_text` LONGTEXT NOT NULL,
			`embedding` VECTOR({$count_vector}) NOT NULL,
			`post_status` VARCHAR(20) NOT NULL DEFAULT 'publish',
			`embedding_date_gmt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

			UNIQUE KEY `ux_object_chunk` (`object_id`, `chunk_index`),
			
			KEY `idx_object` (`object_id`),
			KEY `idx_type_status` (`object_type`, `post_status`),

			VECTOR INDEX (`embedding`) M=16 DISTANCE=cosine
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		//dbDelta($create_sql);
		$res = $wpdb->query( $create_sql );

		if ( false === $res ) {
			error_log( '[zoltiq_agents] CREATE TABLE failed: ' . $wpdb->last_error );
		}

		return true;
	}



	private static function recreate_embedding_tools_table($count_vector) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::DB_TABLE_TOOLS_EMBEDDINGS;

		// Sprawdzenie czy tabela istnieje
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table_name
			)
		);
		
		if ($table_exists === $table_name) {
			
			// Usunięcie tabeli wraz z indeksem VECTOR
			$drop = $wpdb->query(
				"DROP TABLE {$table_name}"
			);

			if ($drop === false) {
				return false;
			}
		}

		// Utworzenie tabeli z nowym wymiarem embedding
		$charset_collate = $wpdb->get_charset_collate();

		$create_sql = "CREATE TABLE `{$table_name}` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`text` LONGTEXT NOT NULL,
			`slug` VARCHAR(255) NOT NULL DEFAULT '',
			`embedding` VECTOR({$count_vector}) NOT NULL,
			`embedding_date_gmt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

			UNIQUE KEY `unique_slug` (`slug`),
			VECTOR INDEX (`embedding`) M=16 DISTANCE=cosine
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		//dbDelta($create_sql);
		$res = $wpdb->query( $create_sql );

		if ( false === $res ) {
			error_log( '[zoltiq_agents] CREATE TABLE failed: ' . $wpdb->last_error );
		}

		return true;
	}





	/**
	 * Entry point for plugin activation hook.
	 */
	public static function activate() {
		
		self::detect_database_version();

	}


	private static function detect_database_version()
	{
		global $wpdb;
		
		$sql = "SELECT
				@@version AS version,
				@@version_comment AS comment
		";

		$db_info = $wpdb->get_row($sql, ARRAY_A);

		$database = [
			'engine'  => 'Unknown',
			'version' => $db_info['version'],
			'vector'  => false,
		];

		// MariaDB
		if (stripos($db_info['version'], 'MariaDB') !== false) {
			$database['engine'] = 'MariaDB';
			if (preg_match('/^([\d\.]+)/', $db_info['version'], $matches)) {
				$database['version'] = $matches[1];
			}
		}

		// MySQL
		/*
		elseif (
			stripos($db_info['comment'], 'mysql') !== false ||
			stripos($db_info['version'], 'mysql') !== false
		) {
			$database['engine'] = 'MySQL';
		}
		*/
		$database['vector'] = self::has_vector_support($database['engine']);

		update_option(self::OPTION_DB_VERSION , $database, true);
		return $database;

	}


	private static function has_vector_support($database_engine)
	{
		
		global $wpdb;

		$queries = [
			'MariaDB' => "
				SELECT VEC_DISTANCE_COSINE(
					VEC_FromText('[1,2]'),
					VEC_FromText('[1,2]')
				)
			"
			/*
			'MySQL' => "
				SELECT DISTANCE(
					STRING_TO_VECTOR('[1,2]'),
					STRING_TO_VECTOR('[1,2]')
				)
			",
			*/
		];

		if (!isset($queries[$database_engine])) {
			return false;
		}

		$wpdb->query($queries[$database_engine]);

		return empty($wpdb->last_error);
	}

}