<?php

namespace Zoltiq\Agents\Interfaces;

use WP_Error;

/**
 * Interface for embedding providers.
 */
interface Embedding_Provider_Interface {

    /**
     * Generate embeddings for given input texts.
     *
     * @param string[] $inputs
     * @return array ['embeddings'=>array] or ['error'=>string]
     */
    public function embeddings(array $inputs): array|WP_Error;

    /**
     * Returns a unified list of available text embedding models.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function list_models_embeddings(): array;
}