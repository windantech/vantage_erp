<?php
/**
 * email_purposes.php
 * --------------------------------------------------------------------------
 * Single source of truth for the purpose-template system.
 *
 * Each PURPOSE has:
 *   - file   : the marked template in email_templates_marked/
 *   - fields : the marker keys that template uses (AI fills these as TEXT)
 *   - defaults : fallback text for each field (never leaves a raw {{marker}})
 *
 * The AI only ever writes the TEXT values for these fields. PHP merges them
 * into the template via str_replace, so structure + footer can never be lost.
 *
 * To add a purpose: add a marked .html file here + an entry below.
 */

if (!defined('VASL_MARKED_TEMPLATE_DIR')) {
    define('VASL_MARKED_TEMPLATE_DIR', __DIR__ . '/email_templates_marked/');
}

$VASL_PURPOSES = [

    // ---- WELCOME / ONBOARDING (photo + schedule/details block) ----
    'welcome' => [
        'file' => 'welcome.html',
        'fields' => [
            'subject_title','headline','greeting','intro',
            'session_focus','learn_intro',
            'learn1_title','learn1_desc','learn2_title','learn2_desc','learn3_title','learn3_desc',
            'schedule_duration','schedule_time','schedule_mode',
            'price_amount','cta_text','cta_link','body','programme_name',
        ],
        'defaults' => [
            'subject_title'   => 'Welcome to Vantage Africa',
            'headline'        => 'Welcome Aboard!',
            'greeting'        => 'Hi $name,',
            'intro'           => 'We are thrilled to welcome you to the programme.',
            'session_focus'   => 'Overview of the programme',
            'learn_intro'     => 'In our opening session we will explore:',
            'learn1_title'    => 'Core concepts', 'learn1_desc' => 'The essential foundations to get you started.',
            'learn2_title'    => 'Practical skills', 'learn2_desc' => 'Hands-on techniques you can apply right away.',
            'learn3_title'    => 'Key roles', 'learn3_desc' => 'Understand the roles that drive success.',
            'schedule_duration' => '5 Weeks, 3 days each week, 1.5 hours daily',
            'schedule_time'   => '8:00pm EAT, 7:00pm CAT',
            'schedule_mode'   => 'Zoom',
            'price_amount'    => '$245 Only',
            'cta_text'        => 'Register Now',
            'cta_link'        => 'http://test_link',
            'body'            => 'I look forward to an exceptional time with you during the training. See you soon.',
            'programme_name'  => 'Training Programme',
        ],
    ],

    // ---- MEET YOUR TRAINER (large photo + bio + credentials) ----
    'meet_trainer' => [
        'file' => 'meet_trainer.html',
        'fields' => [
            'subject_title','headline','greeting','intro',
            'trainer_name','trainer_title','bio1','bio2','quote',
            'cred1','cred2','cred3','cred4',
            'cta_text','cta_link','body','programme_name',
        ],
        'defaults' => [
            'subject_title' => 'Meet Your Trainer',
            'headline'      => 'Meet Your Trainer',
            'greeting'      => 'Hi $name,',
            'intro'         => 'We are delighted to introduce your lead trainer.',
            'trainer_name'  => 'Dr. Benson Kiarie, PhD',
            'trainer_title' => 'Lead Trainer & CEO',
            'bio1'          => 'An accomplished trainer passionate about transformational growth.',
            'bio2'          => 'He has trained thousands of professionals across Africa.',
            'quote'         => 'A robust culture of innovation is key to success in the 21st century.',
            'cred1' => 'PhD in Strategic Management', 'cred2' => 'MBA in Strategic Management',
            'cred3' => 'Certified Public Accountant (CPA-K)', 'cred4' => 'Certified M&E Professional',
            'cta_text' => 'Register for Training', 'cta_link' => 'http://test_link',
            'body'     => 'Get ready to learn from the best. See you in class!',
            'programme_name' => 'Training Programme',
        ],
    ],

    // ---- REMINDER (date/time prominent + still time to register) ----
    'reminder' => [
        'file' => 'reminder.html',
        'fields' => [
            'subject_title','headline','greeting','intro',
            'session_focus','session_date','session_time','session_mode',
            'price_amount','incl1','incl2','incl3','incl4',
            'cta_text','cta_link','body','programme_name',
        ],
        'defaults' => [
            'subject_title' => 'Your Next Class Reminder',
            'headline'      => 'Your Next Class is Coming Up',
            'greeting'      => 'Hi $name,',
            'intro'         => 'This is a friendly reminder that your next session is around the corner.',
            'session_focus' => 'Next Session',
            'session_date'  => 'Monday next week',
            'session_time'  => '8:00pm EAT',
            'session_mode'  => 'Zoom',
            'price_amount'  => '$245 Only',
            'incl1' => '5 weeks of training', 'incl2' => 'Training materials',
            'incl3' => 'Certificate of completion', 'incl4' => 'Membership fee',
            'cta_text' => 'Register Now', 'cta_link' => 'http://test_link',
            'body'     => 'There is still time to join us. See you soon!',
            'programme_name' => 'Training Programme',
        ],
    ],

    // ---- LAST CHANCE (urgency + benefits + pricing) ----
    'last_chance' => [
        'file' => 'last_chance.html',
        'fields' => [
            'subject_title','headline','greeting','intro',
            'benefits_title','benefits_title_hl',
            'benefit1_title','benefit1_desc','benefit2_title','benefit2_desc',
            'benefit3_title','benefit3_desc','benefit4_title','benefit4_desc',
            'price_amount','incl1','incl2','incl3','incl4',
            'cta_text','cta_link','body','programme_name',
        ],
        'defaults' => [
            'subject_title' => 'Last Chance to Register',
            'headline'      => 'Last Chance to Register!',
            'greeting'      => 'Hi $name,',
            'intro'         => 'Time is running out — do not miss this opportunity.',
            'benefits_title' => 'This training will', 'benefits_title_hl' => 'elevate your career',
            'benefit1_title' => 'Career growth', 'benefit1_desc' => 'Climb the ladder faster.',
            'benefit2_title' => 'Practical skills', 'benefit2_desc' => 'Apply what you learn immediately.',
            'benefit3_title' => 'Networking', 'benefit3_desc' => 'Connect with professionals.',
            'benefit4_title' => 'Certification', 'benefit4_desc' => 'Earn a recognized certificate.',
            'price_amount'  => '$245 Only',
            'incl1' => '5 weeks of training', 'incl2' => 'Training materials',
            'incl3' => 'Certificate of completion', 'incl4' => 'Membership fee',
            'cta_text' => 'Register Now', 'cta_link' => 'http://test_link',
            'body'     => 'We cannot wait to see you in the first class!',
            'programme_name' => 'Training Programme',
        ],
    ],
];

/** Resolve a purpose key to a template file path (or '' if missing). */
function vasl_purpose_template_path($purpose)
{
    global $VASL_PURPOSES;
    if (!isset($VASL_PURPOSES[$purpose])) return '';
    $path = VASL_MARKED_TEMPLATE_DIR . $VASL_PURPOSES[$purpose]['file'];
    return is_file($path) ? $path : '';
}
