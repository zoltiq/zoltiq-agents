<?php

namespace Zoltiq\Agents\Interfaces;

use WP_Error;

/**
 * Interface for LLM client implementations.
 *
 * Defines a unified contract for all Large Language Model providers
 * (e.g. OpenAI, Gemini, Anthropic, Ollama).
 *
 * Implementations of this interface act as adapters for specific APIs,
 * allowing the application to remain provider-agnostic.
 */
interface Llm_Client_Interface {
    
    /**
     * Returns a unified list of available models.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function list_models(): array;

    /**
     * Sends a request to the LLM and returns the response.
     *
     * This method acts as a generic entry point for interacting with the model.
     * The payload structure may differ between providers, but should be passed
     * as a JSON-encoded string.
     *
     * Implementations are responsible for:
     * - Sending the request to the correct API endpoint
     * - Handling authentication
     * - Returning a decoded response
     *
     * @param string $endpoint API endpoint (provider-specific).
     * @param string $body     JSON-encoded request payload.
     *
     * @return array|WP_Error Decoded response or WordPress error object.
     */
    public function generate_response(string $endpoint, string $body): array|WP_Error;
}


