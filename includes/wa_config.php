<?php
/**
 * WhatsApp module config. Copy to includes/wa_config.php and fill in.
 * includes/wa_config.php is gitignored — never commit real keys.
 *
 *   cp includes/wa_config.sample.php includes/wa_config.php
 *
 * Admin pages get the DB connection ($conn) from auth.php (via header.php).
 * The DB_* values here are ONLY used by the webhook (wa_webhook.php), which runs
 * without a session — set them to the same values as auth.php.
 */

// ---- DB (for the headless webhook only; same as auth.php) ----
define('WA_DB_HOST', 'localhost');
define('WA_DB_USER', 'vantage_crmuser');
define('WA_DB_PASS', '4_dqmv4yZc8yl%PM');
define('WA_DB_NAME', 'vantage_crm');

// ---- 360dialog (WhatsApp Cloud API) ----
define('WA_DIALOG_KEY', 'X2ielJobe9qoPrbDIffgd1LYAK');
define('WA_DIALOG_URL', 'https://waba-v2.360dialog.io');
define('WA_PHONE',      '254796128454');
define('WA_VERIFY_TOKEN','X2ielJobe9qoPrbDIffgd1LYAK');           // optional ?token=... gate for the webhook

// ---- AI providers (admin picks the active one on wa_settings.php) ----
define('WA_OPENAI_KEY',   'YOUR_OPENAI_API_KEY');
define('WA_OPENAI_MODEL', 'gpt-4o-mini');
define('WA_OPENAI_URL',   'https://api.openai.com');

define('WA_ANTHROPIC_KEY',     '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');
define('WA_ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001');
define('WA_ANTHROPIC_URL',     'https://api.anthropic.com');
define('WA_ANTHROPIC_VERSION', '2023-06-01');

define('WA_DEFAULT_PROVIDER', 'claude');  // 'claude' | 'openai'

// ---- Access ----
define('WA_ROLE', 44);                    // ERP role that may use the module
