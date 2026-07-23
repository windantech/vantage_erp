<?php
/**
 * lead_ai.php  —  VASL Lead Intelligence (Phase 4)
 *
 * On-demand AI message/approach generation for a single lead. Called from the
 * dashboard when a staff member clicks "Suggest message" — NOT from the cron.
 * This keeps cost proportional to actual usage and limits PII sent to OpenAI
 * to only the leads being worked.
 *
 * Generated text is cached in lead_insights.ai_suggestion so it's never
 * regenerated unless the staff member explicitly asks for a refresh.
 *
 * SECURITY / COMPLIANCE NOTES:
 *   - We send the minimum lead fields needed (name, country, org, position,
 *     program, segment). We do NOT send email or phone to the model.
 *   - KVDPA: this transmits prospect data to a US service on demand. Keep the
 *     leadership compliance note in mind before wide rollout.
 *
 * Include AFTER conn.php + lead_helpers.php.
 */

if (!defined('VASL_LEAD_AI')) {
    define('VASL_LEAD_AI', 1);
}

/* ------------------------------------------------------------------ */
/*  CONFIG                                                             */
/*  Set your key in one of these ways (checked in order):              */
/*    1. Environment variable OPENAI_API_KEY                            */
/*    2. A file ai_config.php in this folder defining VASL_OPENAI_KEY   */
/*  ai_config.php should NOT be committed to source control.           */
/* ------------------------------------------------------------------ */
function lead_ai_key(): string {
    if (!empty(getenv('OPENAI_API_KEY'))) return getenv('OPENAI_API_KEY');
    $cfg = __DIR__ . '/ai_config.php';
    if (is_file($cfg)) {
        require_once $cfg;
        if (defined('VASL_OPENAI_KEY') && VASL_OPENAI_KEY !== '') return VASL_OPENAI_KEY;
    }
    return '';
}

define('VASL_OPENAI_MODEL', 'gpt-4o-mini');   // cheap, capable; change if desired
define('VASL_AI_TIMEOUT', 30);

/* ------------------------------------------------------------------ */
/*  FEW-SHOT: profiles of leads who actually converted                 */
/*  We pull a small, diverse sample of converted leads so the model    */
/*  sees the kind of prospect that tends to enrol/pay.                 */
/* ------------------------------------------------------------------ */
function lead_ai_converted_examples(mysqli $conn, int $limit = 6): array {
    $out = [];
    // Diversify across segments by ordering on a pseudo-random within converted set
    $sql = "SELECT lead_segment, country_norm, organization, position, program_or_term, source
            FROM lead_insights
            WHERE is_converted = 1
              AND (organization <> '' OR position <> '')
            ORDER BY RAND()
            LIMIT " . (int)$limit;
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $out[] = sprintf(
                "- %s from %s%s, interested in \"%s\" (%s) — converted to paying client.",
                $r['position'] ?: ($r['lead_segment'] ?: 'professional'),
                $r['country_norm'] ?: 'unknown country',
                $r['organization'] ? " at " . $r['organization'] : '',
                $r['program_or_term'] ?: 'a programme',
                $r['source'] === 'virtual' ? 'online course' : 'international event'
            );
        }
        $res->free();
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/*  BUILD PROMPT  (no email/phone — minimised PII)                     */
/* ------------------------------------------------------------------ */
function lead_ai_build_messages(array $lead, array $examples): array {
    $org  = $lead['organization'] ?: 'their organization';
    $pos  = $lead['position'] ?: 'professional';
    $ctry = $lead['country_norm'] ?: 'their country';
    $prog = $lead['program_or_term'] ?: 'one of our programmes';
    $kind = ($lead['source'] ?? '') === 'virtual' ? 'online course' : 'international leadership event';
    $name = $lead['fullname'] ?: 'there';
    $first = trim(explode(' ', $name)[0]);

    $exampleBlock = $examples
        ? "Here are profiles of past inquirers who became paying clients, to guide your tone and angle:\n" . implode("\n", $examples)
        : "";

    $system = "You are a business development assistant for Vantage Africa School of Leadership (VASL), "
        . "a Kenyan institution offering online leadership courses (Virtual department) and international "
        . "leadership events/congresses (International department). You help staff follow up with prospects "
        . "who inquired but have not yet enrolled or paid. Your tone is warm, professional, and concise — "
        . "never pushy or salesy. You write for an East African and international professional audience.";

    $user = "Draft a follow-up for this inquiry.\n\n"
        . "LEAD PROFILE:\n"
        . "- Name: {$name}\n"
        . "- Role: {$pos}\n"
        . "- Organization: {$org}\n"
        . "- Country: {$ctry}\n"
        . "- Interested in: {$prog} ({$kind})\n"
        . "- Segment: " . ($lead['lead_segment'] ?: 'n/a') . "\n\n"
        . ($exampleBlock ? $exampleBlock . "\n\n" : "")
        . "Produce exactly this structure in plain text (no markdown headers):\n"
        . "APPROACH: 1-2 sentences on the best angle for this specific prospect.\n"
        . "EMAIL: a short ready-to-send follow-up email (greeting to {$first}, 90-140 words, "
        . "one clear call to action). Do not invent prices, dates, or facts you weren't given; "
        . "keep claims general.";

    return [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $user],
    ];
}

/* ------------------------------------------------------------------ */
/*  CALL OPENAI                                                        */
/*  Returns ['ok'=>bool, 'text'=>string, 'error'=>string]              */
/* ------------------------------------------------------------------ */
function lead_ai_call(array $messages): array {
    $key = lead_ai_key();
    if ($key === '') {
        return ['ok' => false, 'error' => 'OpenAI API key not configured. Add ai_config.php with VASL_OPENAI_KEY.'];
    }

    $payload = json_encode([
        'model'       => VASL_OPENAI_MODEL,
        'messages'    => $messages,
        'temperature' => 0.7,
        'max_tokens'  => 500,
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => VASL_AI_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'error' => 'Network error: ' . $cerr];
    }
    $data = json_decode($resp, true);
    if ($code !== 200) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $code);
        return ['ok' => false, 'error' => 'OpenAI: ' . $msg];
    }
    $text = $data['choices'][0]['message']['content'] ?? '';
    if (trim($text) === '') {
        return ['ok' => false, 'error' => 'Empty response from model'];
    }
    return ['ok' => true, 'text' => trim($text)];
}

/* ------------------------------------------------------------------ */
/*  HIGH-LEVEL: generate (or fetch cached) suggestion for a lead       */
/* ------------------------------------------------------------------ */
function lead_ai_get_suggestion(mysqli $conn, string $source, string $sourceId, bool $force = false): array {
    // Fetch the lead row
    $stmt = $conn->prepare("SELECT fullname, country_norm, organization, position,
                                   program_or_term, lead_segment, source,
                                   ai_suggestion, ai_generated_at
                            FROM lead_insights WHERE source = ? AND source_id = ?");
    $stmt->bind_param('ss', $source, $sourceId);
    $stmt->execute();
    $lead = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$lead) return ['ok' => false, 'error' => 'Lead not found'];

    // Return cached unless forced
    if (!$force && !empty($lead['ai_suggestion'])) {
        return ['ok' => true, 'text' => $lead['ai_suggestion'],
                'cached' => true, 'generated_at' => $lead['ai_generated_at']];
    }

    $examples = lead_ai_converted_examples($conn, 6);
    $messages = lead_ai_build_messages($lead, $examples);
    $result   = lead_ai_call($messages);

    if (!$result['ok']) return $result;

    // Cache it
    $now = date('Y-m-d H:i:s');
    $up = $conn->prepare("UPDATE lead_insights
                          SET ai_suggestion = ?, ai_generated_at = ?
                          WHERE source = ? AND source_id = ?");
    $up->bind_param('ssss', $result['text'], $now, $source, $sourceId);
    $up->execute();
    $up->close();

    return ['ok' => true, 'text' => $result['text'], 'cached' => false, 'generated_at' => $now];
}
