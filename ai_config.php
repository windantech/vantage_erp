<?php
/**
 * ai_config.php
 * Server-side OpenAI configuration. Keep this file OUT of source control
 * (add it to .gitignore) since it contains a secret key.
 *
 * 1) Paste your OpenAI API key below.
 * 2) Optionally adjust the model.
 *
 * This file is included by email_ai_generate.php.
 */

// ====== EDIT THIS: your OpenAI API key ======
if (!defined('VASL_OPENAI_KEY')) {
    define('VASL_OPENAI_KEY', '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');
}

// Model used for email generation
if (!defined('VASL_OPENAI_MODEL')) {
    define('VASL_OPENAI_MODEL', 'gpt-4o');
}

define('VASL_ANTHROPIC_KEY', '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');         // your Console key
define('VASL_ANTHROPIC_MODEL', 'claude-sonnet-4-6'); // optional; this is the default