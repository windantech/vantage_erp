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
define('WA_DB_PASS', 'CHANGE_ME');
define('WA_DB_NAME', 'vantage_crm');

// ---- 360dialog (WhatsApp Cloud API) ----
define('WA_DIALOG_KEY', 'YOUR_360DIALOG_API_KEY');
define('WA_DIALOG_URL', 'https://waba-v2.360dialog.io');
define('WA_PHONE',      '254XXXXXXXXX');
define('WA_VERIFY_TOKEN', '');           // optional ?token=... gate for the webhook
define('WA_CRON_TOKEN',   '');           // ?token=... gate for wa_cron.php (falls back to WA_VERIFY_TOKEN if blank)

// ---- AI providers (admin picks the active one on wa_settings.php) ----
define('WA_OPENAI_KEY',   'YOUR_OPENAI_API_KEY');
define('WA_OPENAI_MODEL', 'gpt-4o-mini');
define('WA_OPENAI_URL',   'https://api.openai.com');

define('WA_ANTHROPIC_KEY',     'YOUR_ANTHROPIC_API_KEY');
define('WA_ANTHROPIC_MODEL',   'claude-haiku-4-5-20251001');   // fast + cheap; best for the webhook window
define('WA_ANTHROPIC_URL',     'https://api.anthropic.com');
define('WA_ANTHROPIC_VERSION', '2023-06-01');

define('WA_DEFAULT_PROVIDER', 'claude');  // 'claude' | 'openai'

// ---- Website (used to auto-draft course knowledge bases) ----
define('WA_SITE_URL', 'https://vantageafricaleaders.com');

// ---- Access ----
define('WA_ROLE', 44);                    // ERP role that may use the module

// ---- Enrollment (guided capture -> direct enroll). Toggle is the wa_settings
//      'enroll_enabled' flag (admin), off by default. Moodle is OPTIONAL: leave
//      WA_MOODLE_HOST blank to skip Moodle account creation. Same DB as the
//      website's database/conn.php $moodle_conn if you use it. ----
define('WA_MOODLE_HOST', '');
define('WA_MOODLE_USER', '');
define('WA_MOODLE_PASS', '');
define('WA_MOODLE_NAME', '');
