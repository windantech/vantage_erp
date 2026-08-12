<?php
/**
 * receive_corporate_proposal.php  —  system-of-record receiver for corporate-training
 * proposal requests submitted on the public website (corporate-proposal.php).
 *
 * FLOW (agreed with frontend): the website form posts to its own corporate-proposal.php,
 * which (a) sends the CEO notification email as it does today, then (b) server-to-server
 * cURL-POSTs the payload here with a shared-secret header. Frontend soft-fails: if this
 * endpoint is down it still sends the CEO email and logs the forward failure, so no lead
 * is lost.
 *
 * CONTRACT
 *   Method:  POST  (application/x-www-form-urlencoded or multipart/form-data)
 *   Auth:    header  X-Vantage-Proposal-Secret: <shared secret>
 *   Required: contact_name, contact_email, contact_phone, org_name, org_country,
 *             org_sector, participants_count, preferred_delivery
 *   Optional: org_size, city, preferred_dates, budget_range, audience_profile,
 *             areas_of_interest (JSON array string OR repeated areas_of_interest[]),
 *             additional_notes
 *   Responses (always JSON, Content-Type: application/json):
 *     200  {"success":true,"id":123,"reference":"CP-123"}
 *     401  {"success":false,"error":"unauthorized"}
 *     405  {"success":false,"error":"method not allowed"}
 *     422  {"success":false,"error":"Please fill in all required fields ..."}
 *     500  {"success":false,"error":"..."}
 *
 * The shared secret lives in an untracked, server-only file (never committed — same rule
 * as the Brevo key):  includes/proposal_config.php  ->  <?php $PROPOSAL_SHARED_SECRET='...';
 * See includes/proposal_config.sample.php. The frontend is given that value out-of-band and
 * sends it in the X-Vantage-Proposal-Secret header; it never reads our DB.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function proposal_respond($code, array $payload)
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// ---- Method ----
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    proposal_respond(405, ['success' => false, 'error' => 'method not allowed']);
}

// ---- Auth: shared secret (server-only file includes/proposal_config.php, gitignored) ----
$expected_secret = '';
$cfg = __DIR__ . '/proposal_config.php';
if (is_file($cfg)) {
    include $cfg;
    if (!empty($PROPOSAL_SHARED_SECRET)) {
        $expected_secret = (string) $PROPOSAL_SHARED_SECRET;
    }
}
$provided_secret = $_SERVER['HTTP_X_VANTAGE_PROPOSAL_SECRET'] ?? '';
if ($expected_secret === '' || !is_string($provided_secret) || !hash_equals($expected_secret, $provided_secret)) {
    proposal_respond(401, ['success' => false, 'error' => 'unauthorized']);
}

// ---- DB ----
require __DIR__ . '/../../database/conn.php';   // provides $conn (vantage_crm)
if (!isset($conn) || !$conn) {
    proposal_respond(500, ['success' => false, 'error' => 'database unavailable']);
}

// ---- Ensure the table exists (self-provisioning; idempotent) ----
$conn->query(
    "CREATE TABLE IF NOT EXISTS `corporate_proposals` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `contact_name` VARCHAR(190) NOT NULL,
        `contact_email` VARCHAR(190) NOT NULL,
        `contact_phone` VARCHAR(60) NOT NULL,
        `org_name` VARCHAR(190) NOT NULL,
        `org_country` VARCHAR(100) NOT NULL,
        `org_sector` VARCHAR(120) NOT NULL,
        `org_size` VARCHAR(60) DEFAULT NULL,
        `city` VARCHAR(120) DEFAULT NULL,
        `participants_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `preferred_delivery` VARCHAR(80) NOT NULL,
        `preferred_dates` VARCHAR(190) DEFAULT NULL,
        `budget_range` VARCHAR(120) DEFAULT NULL,
        `audience_profile` TEXT DEFAULT NULL,
        `areas_of_interest` TEXT DEFAULT NULL,
        `additional_notes` TEXT DEFAULT NULL,
        `status` ENUM('new','contacted','proposal_sent','won','lost') NOT NULL DEFAULT 'new',
        `assigned_to` INT DEFAULT NULL,
        `source` VARCHAR(60) DEFAULT 'website',
        `submitted_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`),
        KEY `idx_email` (`contact_email`),
        KEY `idx_submitted` (`submitted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// ---- Collect + validate ----
$field = function ($k) {
    return isset($_POST[$k]) && !is_array($_POST[$k]) ? trim((string) $_POST[$k]) : '';
};

$required = ['contact_name', 'contact_email', 'contact_phone', 'org_name',
            'org_country', 'org_sector', 'participants_count', 'preferred_delivery'];
foreach ($required as $r) {
    if ($field($r) === '') {
        proposal_respond(422, ['success' => false,
            'error' => 'Please fill in all required fields (missing: ' . $r . ').']);
    }
}
if (!filter_var($field('contact_email'), FILTER_VALIDATE_EMAIL)) {
    proposal_respond(422, ['success' => false, 'error' => 'A valid contact_email is required.']);
}

// areas_of_interest: accept repeated areas_of_interest[], a JSON array string, or CSV.
$areas = [];
if (isset($_POST['areas_of_interest'])) {
    if (is_array($_POST['areas_of_interest'])) {
        $areas = $_POST['areas_of_interest'];
    } else {
        $raw = (string) $_POST['areas_of_interest'];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $areas = $decoded;
        } elseif (trim($raw) !== '') {
            $areas = explode(',', $raw);
        }
    }
}
$areas = array_values(array_filter(array_map(function ($a) {
    return trim((string) $a);
}, $areas), function ($a) {
    return $a !== '';
}));
$areas_json = json_encode($areas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Normalised scalar values
$contact_name       = $field('contact_name');
$contact_email      = $field('contact_email');
$contact_phone      = $field('contact_phone');
$org_name           = $field('org_name');
$org_country        = $field('org_country');
$org_sector         = $field('org_sector');
$org_size           = $field('org_size') !== '' ? $field('org_size') : null;
$city               = $field('city') !== '' ? $field('city') : null;
$participants_count = (int) preg_replace('/[^0-9]/', '', $field('participants_count'));
$preferred_delivery = $field('preferred_delivery');
$preferred_dates    = $field('preferred_dates') !== '' ? $field('preferred_dates') : null;
$budget_range       = $field('budget_range') !== '' ? $field('budget_range') : null;
$audience_profile   = $field('audience_profile') !== '' ? $field('audience_profile') : null;
$additional_notes   = $field('additional_notes') !== '' ? $field('additional_notes') : null;

// ---- Insert (prepared) ----
$sql = "INSERT INTO `corporate_proposals`
        (contact_name, contact_email, contact_phone, org_name, org_country, org_sector,
         org_size, city, participants_count, preferred_delivery, preferred_dates,
         budget_range, audience_profile, areas_of_interest, additional_notes,
         status, source, submitted_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'new', 'website', NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    proposal_respond(500, ['success' => false, 'error' => 'could not prepare insert']);
}
$stmt->bind_param(
    'ssssssssissssss',
    $contact_name, $contact_email, $contact_phone, $org_name, $org_country, $org_sector,
    $org_size, $city, $participants_count, $preferred_delivery, $preferred_dates,
    $budget_range, $audience_profile, $areas_json, $additional_notes
);

if ($stmt->execute()) {
    $id = $stmt->insert_id;
    $stmt->close();
    proposal_respond(200, ['success' => true, 'id' => (int) $id, 'reference' => 'CP-' . $id]);
}

error_log('[corporate-proposal] insert failed: ' . $conn->error);
$stmt->close();
proposal_respond(500, ['success' => false, 'error' => 'could not store proposal']);
