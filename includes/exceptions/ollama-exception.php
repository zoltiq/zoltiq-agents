<?php

namespace Zoltiq\Agents\Exceptions;

/**
 * Exception for Ollama API errors.
 *
 * This exception is thrown when an error occurs while communicating
 * with the Ollama API or processing its response.
 *
 * It extends the base PHP Exception class and implements Llm_Exception,
 * allowing unified handling of all LLM-related exceptions.
 */
class Ollama_Exception extends \Exception implements Llm_Exception {} 