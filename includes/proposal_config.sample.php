<?php
/**
 * TEMPLATE — copy this to `proposal_config.php` (same folder) ON THE SERVER and set a
 * strong secret. `proposal_config.php` is gitignored and must NEVER be committed.
 *
 *   cp includes/proposal_config.sample.php includes/proposal_config.php
 *   # then edit and set a real value, e.g. output of:  openssl rand -hex 32
 *
 * The SAME value must be given to the frontend, which sends it on every forward as:
 *   X-Vantage-Proposal-Secret: <this value>
 *
 * receive_corporate_proposal.php rejects any request whose header doesn't match
 * (constant-time compare) with HTTP 401.
 */

$PROPOSAL_SHARED_SECRET = 'CHANGE_ME_to_a_long_random_string';
