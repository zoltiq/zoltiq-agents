<?php

namespace Zoltiq\Agents\Exceptions;

/**
 * Exception for Google Gemini API errors.
 *
 * This exception is thrown when an error occurs while communicating
 * with the Gemini API or processing its response.
 *
 * It extends the base PHP Exception class and implements Llm_Exception,
 * allowing unified handling of all LLM-related exceptions.
 */
class Gemini_Exception extends \Exception implements Llm_Exception {}