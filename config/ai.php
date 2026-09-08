<?php
/**
 * StudyMe AI-Powered Learning Platform - AI Configuration
 * 
 * Secure configuration for OpenAI API access and AI model settings.
 * NEVER hardcode API keys in source code or expose to client-side scripts.
 */

// Load OpenAI Model identifier (defaults to gpt-5.6-luna)
if (!defined('OPENAI_MODEL')) {
    $configuredModel = env('OPENAI_MODEL', 'gpt-5.6-luna');
    define('OPENAI_MODEL', !empty($configuredModel) ? $configuredModel : 'gpt-5.6-luna');
}

/**
 * Retrieve the OpenAI API key securely on the server side.
 * 
 * Returns the key string from environment if set, or empty string.
 * This should ONLY be called from backend PHP services/controllers, never exposed in JSON responses, HTML, or JavaScript.
 *
 * @return string
 */
function get_openai_api_key(): string {
    $key = env('OPENAI_API_KEY');
    if ($key !== null && $key !== '') {
        return trim((string)$key);
    }
    return '';
}

/**
 * Retrieve the active AI model name.
 *
 * @return string
 */
function get_openai_model(): string {
    return defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-5.6-luna';
}

/**
 * Check if the OpenAI integration is configured with an API key.
 *
 * @return bool
 */
function is_openai_configured(): bool {
    return get_openai_api_key() !== '';
}
