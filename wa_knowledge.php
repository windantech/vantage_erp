<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

// WhatsApp users (role 44) may view; editing stays supervisor-only (enforced in includes/wa_process.php).
if (!in_array(WA_ROLE, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Access denied.</div></div>';
    require_once 'footer.php';
    exit;
}

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);

// Which course/event is selected (?ref=course:12 or ?ref=event:3).
$selType = null; $selId = 0;
$ref = $_GET['ref'] ?? '';
if (strpos($ref, ':') !== false) {
    list($t, $i) = explode(':', $ref, 2);
    $selType = in_array($t, ['event', 'program'], true) ? $t : 'course';
    $selId   = (int)$i;
}

$courses  = wa_active_courses($conn);
$events   = wa_active_events($conn);
$programs = wa_programs_list($conn);
$kbBody  = ($selType && $selId) ? wa_knowledge_get($conn, $selType, $selId) : '';
$kbAi    = ($selType && $selId) ? wa_knowledge_get_ai($conn, $selType, $selId) : '';
$learnings = ($selType && $selId) ? wa_kb_learnings_pending($conn, $selType, $selId) : [];
$selName = ($selType && $selId) ? wa_ref_name($conn, $selType, $selId) : '';
$providerReady = wa_provider_ready(wa_active_provider($conn));
// Event authoring: linked programme + details pulled straight from the system.
$evProgramId = ($selType === 'event' && $selId) ? wa_event_program_get($conn, $selId) : 0;
$evCity = $evCost = $evReg = $evDates = '';
if ($selType === 'event' && $selId) {
    $er   = mysqli_query($conn, "SELECT start_on, end_on, location, COALESCE(early_amount,0) AS early_amount FROM `Event` WHERE event_id = " . (int)$selId . " LIMIT 1");
    $erow = $er ? (mysqli_fetch_assoc($er) ?: []) : [];
    $loc  = trim((string)($erow['location'] ?? ''));
    $evCity  = strpos($loc, 'ACADEMIC#') === 0 ? '' : $loc;
    $amt     = (float)($erow['early_amount'] ?? 0);
    $evCost  = $amt > 0 ? ('USD ' . rtrim(rtrim(number_format($amt, 2), '0'), '.')) : '';
    $evDates = wa_event_when_range($erow['start_on'] ?? '', $erow['end_on'] ?? '');
    $evReg   = wa_register_link($conn, 'event', $selId);
}

// Ready-made events M&E (CMEP International Programme) knowledge, loadable into the
// M&E Trainings programme (or an event) via the "Load M&E events knowledge" button.
$mneEventsKb = <<<'MNEKB'
# Certified Monitoring and Evaluation Professional (CMEP) Training — International Programme
## AI Assistant Knowledge Base — EVENTS ONLY

> Legend: Questions marked ESCALATE must not be answered/resolved by the AI alone — hand off to a human staff member.

## 0. Scope — READ FIRST (mandatory)
This knowledge base applies ONLY to: Certified Monitoring and Evaluation Professional (CMEP) Training — International Programme, EVENTS category (3-day in-person training + 3-month follow-up, country cohorts).
It does NOT apply to: virtual/online-only programmes, academic programmes (diplomas, degrees, university partnerships), corporate in-house training, or any other Vantage Africa product line.
Routing rule: If the client's inquiry is about a virtual programme, an academic programme, or any non-events offering — do NOT answer from this document and do NOT adapt or infer answers from it (fees, dates, certificates, payment channels and inclusions are events-specific and wrong elsewhere). Reply: "Let me connect you with the right team for that programme," and ESCALATE to a human.
Ambiguity rule: If it is unclear whether the inquiry is about an events cohort, ask ONE clarifying question first: "Are you asking about our in-person CMEP training cohort, or one of our online/academic programmes?" Do not answer until confirmed.
No-invention rule: If the answer is not in this document, say "Let me confirm that for you" and escalate. Never improvise dates, fees, venues or payment details.
Prices, dates, inclusions, payment details and certificates in this document are valid for EVENTS cohorts only.

## 1. Basics
Q: What is this M&E course actually about?
A: A practical Monitoring and Evaluation programme that helps you design systems for tracking project progress, measuring results and using evidence to improve decisions. You learn by applying each concept to a real or case-study project and building the main M&E tools needed in the field.

Q: Who is this course for?
A: NGO and development professionals, government officers, project and programme teams, researchers, consultants, private-sector professionals, students and anyone moving into M&E. Beginners are welcome; experienced practitioners can strengthen and formalise their skills.

Q: How long is the course and how is it delivered?
A: Blended format: three days of face-to-face, hands-on training in the host country, followed by three months of online learning and support. During follow-up, participants normally meet once a week in the evening and continue their practical assignments and M&E plan.

Q: Who teaches the course?
A: Experienced Vantage Africa M&E trainers and practitioners. Dr. Benson Kiarie may lead or support sessions with other qualified facilitators; the exact lead trainer for each country cohort is confirmed in the cohort announcement.

Q: How big are the classes?
A: About 15 to 40 participants — large enough for networking and peer learning while still allowing practical exercises, questions and facilitator feedback.

Q: Is the course only in English?
A: The standard language of instruction is English. Any translation/alternative-language support is cohort-specific and only promised after the international team confirms it.

## 2. Enrollment / Logistics
Q: What time are the live classes?
A: The three physical training days follow the timetable announced for the specific country and cohort. The three-month online follow-up sessions are normally held once a week in the evening; exact day, time and time zone are shared with registered participants.

Q: I'm not in Kenya — what time will class be for me?
A: The physical training is in the advertised host country, so the daily timetable is given in that country's local time. For the online follow-up sessions, we share the confirmed time and time-zone conversion with each cohort before the first session.

Q: What happens right after I pay?
A: After payment is confirmed, the team records your registration, sends your receipt/confirmation, and shares onboarding steps. You then receive cohort updates, the physical venue information, access to the participant group and, when ready, the e-learning platform.

Q: How do I register for the course?
A: Complete the official registration form or contact the programme coordinator with your full name, email, phone, country, organisation and position. Make the USD 80 deposit, the first installment or the full payment through an approved channel; the team then confirms your place and onboarding. Registration: contact the programme coordinator for the current cohort registration link.

Q: Do I need experience or documents to join?
A: No previous M&E experience is required. You normally just need your correct personal and professional details; a CV/transcript is not normally required unless the cohort says otherwise.

Q: Where do I get the online follow-up link?
A: Shared with registered participants through the official cohort WhatsApp group or another approved channel, normally before each session with the time and any preparation needed.

Q: Is there a smaller group or community once I register?
A: Yes — registered participants are added to a cohort communication group for announcements, links, assignments and support. You also receive one year of membership in the Vantage Africa M&E Professionals Association.

Q: Once I pay, do I need to wait for classes to start to begin learning?
A: The team onboards you per the cohort schedule. Some resources/orientation may be shared before the physical training; full e-learning access is provided for three months. The coordinator confirms the exact activation date.

Q: Do I need any special software or a strong internet connection?
A: A device that can access the e-learning platform and weekly online sessions plus a stable connection. A laptop is strongly recommended for developing M&E tools; a web browser, PDF reader, spreadsheet software and the stated meeting app are usually sufficient unless the facilitator says otherwise.

Q: Who do I contact if I have a problem after I've registered?
A: The official programme coordinator/support contact shared in your registration confirmation and cohort group. For payment disputes, refunds, complaints or certificate problems, ask for direct staff assistance rather than relying only on the AI.

Q: What's the very first thing that happens once I express interest?
A: The team confirms which country/cohort you want and shares current dates, venue, fee and registration instructions. You provide your registration details, choose the USD 80 deposit, the first installment or the full fee, and receive confirmation after payment is verified.

Q: Where is the physical training held?
A: At the venue announced for the specific international cohort in the host city and country. Always use the current cohort page or coordinator confirmation for the exact venue, address and directions.

Q: What should I bring to the three-day physical training?
A: A laptop and charger, a notebook and pen, your registration/payment confirmation, and details of the real or case-study project you want to use. Bring anything you need for a full training day and follow venue-specific instructions.

Q: Do I need to bring a laptop?
A: Strongly recommended — the course involves developing frameworks, plans, tables and reports. Bring your charger and, where possible, a backup power option; the team advises on any extra requirements.

Q: How do I confirm the current dates, venue and coordinator?
A: These change by country and cohort. Use the active programme announcement, official registration page or the international M&E team's confirmation, and make sure the dates, city, venue, daily schedule, registration link and contact number match your intended cohort.

Q: Can I join a later cohort if I can't make this one?
A: Yes — cohorts run in different countries at different times. The team can share the next option, but any transfer of an existing payment must be confirmed by a staff member first.

Q: What if I have technical issues with the platform/online sessions?
A: Contact the coordinator/support contact in your cohort group. Include your name, email, device and a screenshot of the error where possible.

Q: Can I get access to the training schedule?
A: Course outline / schedule: https://drive.google.com/file/d/1iGnMBGLw_lLdazONUBW6GS8KMKyVE_47/view?usp=drive_link

Q: Can I talk to someone before enrolling?
A: Yes — WhatsApp/call +254 102 179 174, or email john@vantageafricaleaders.com.

Q: Is there a WhatsApp group for this course?
A: Yes — once you register and confirm payment, you're added to the private WhatsApp group for your intake.

Q: When is the next course/intake starting? (ESCALATE)
A: Needs confirmation. Say you'll confirm the current open intake date rather than guessing. ESCALATE to a human.

## 3. Content / Outcomes
Q: What topics will I actually learn?
A: Project/programme foundations, Theory of Change, core M&E concepts, goals and SMART objectives, indicators, baselines and targets, Logframes and Results Frameworks, Performance Monitoring and Evaluation Plans, data collection and quality, analysis, reporting, participatory M&E, sustaining M&E systems and M&E consultancy. The international package also includes Resource Mobilization and Proposal Writing.

Q: What if I miss a live class?
A: Notify the coordinator as early as possible. Plan to attend all three physical days — the hands-on exercises and group work are core, and a missed physical day may not be fully replaced by a recording. For an online follow-up session, the team advises on catch-up materials and assignment requirements.

Q: Will I get the class notes and recordings?
A: Yes — training materials and three months' access to the e-learning platform (notes, tools, templates, prerecorded lessons, quizzes and available session recordings). Do not promise a full recording of every physical training session.

Q: Is there any way to check my own progress?
A: Yes — the e-learning platform has module quizzes and activities, and facilitators review your practical work and M&E plan. Your completed tools show whether you can apply the concepts, not just define them.

Q: How long can I access the course materials?
A: Three months' access to the e-learning platform and three months of post-training support. Any extension must be confirmed by the programme team.

Q: Can I ask questions during class, or is it just lectures?
A: It's interactive — physical sessions include guided exercises, discussions, group work and feedback on participant projects; the online follow-up adds more opportunities to ask questions and improve your M&E plan.

Q: Will I still learn if I only attend recordings and not the live sessions?
A: The online resources are valuable, but the programme is designed around live, practical participation. Recordings and notes cannot fully replace the three physical days, project exercises, peer discussions and facilitator feedback, so a recordings-only plan may not meet certification requirements.

Q: Do I also receive Proposal Writing training?
A: Yes — Resource Mobilization and Proposal Writing is included; successful participants receive a separate Proposal Writing certificate in addition to the M&E certificate.

Q: What is the Vantage Africa M&E Professionals Association membership?
A: One year of membership connecting participants with a wider professional community for networking, learning resources and continued engagement after the formal training.

Q: Do I need to choose a project for the training?
A: Yes — select a real project you're implementing or a case-study project you care about, and use it throughout the programme to build a complete M&E plan.

Q: What practical tools will I produce?
A: A Theory of Change, goals and SMART objectives, indicators, a Logframe, a Results Framework, a Performance Monitoring Plan, a Performance Evaluation Plan and relevant reporting/data-quality tools for your selected project.

E-Learning Platform
Q: Do I get printed materials?
A: Materials are digital via the e-learning platform (notes, templates, available recordings). You're welcome to print anything yourself.
Q: Do I get lifetime access to materials?
A: No — you get three months' access. Any extension must be confirmed by the programme team.
Q: Are the sessions recorded?
A: Online follow-up sessions are normally recorded and posted where available. The three physical training days are hands-on and are NOT guaranteed to be recorded.
Q: Do I get access to a mentor after the course?
A: You receive three months of post-training online support and three months of e-learning access, and you're welcome to reach out with follow-up questions after that.

## 4. Pricing / Payment
Q: How much does the course cost?
A: The international programme fee is USD 580. Where local-currency payment is available, the coordinator shares the approved equivalent and official payment instructions.

Q: What is included in the USD 580 fee?
A: The three-day physical training and consultancy support, training materials, meals for the three physical days, three months of post-training online support, three months of e-learning access, one year of Vantage Africa M&E Professionals Association membership, Resource Mobilization and Proposal Writing training, and two certificates.

Q: Are there any extra charges?
A: No hidden charges — the USD 580 fee covers all core materials, live sessions, e-learning access and your certificates.

Q: Are accommodation, transport, flights or visas included?
A: No — participants are normally responsible for their own accommodation, local transport, flights, visas, insurance and other personal travel costs unless a cohort package clearly says otherwise.

Q: Are meals included during the physical training?
A: Yes — the standard USD 580 package includes meals during the three physical training days. Meal arrangements and dietary requests are communicated by the cohort team.

Q: Can I pay in installments? (Only raise installments if the client asks, or says the cost is too high.)
A: Yes — two installments: pay at least half (USD 290) before the physical training, then the remaining USD 290 within the three-month post-training period. An initial USD 80 deposit reserves your place and forms part of the fee.

Q: Can I reserve my place with USD 80?
A: Yes — a USD 80 deposit reserves your place and is part of the total USD 580. Then top up so at least half (USD 290) is paid before the physical training.

Q: Can I switch from partial to full payment later, or vice versa?
A: You can top up at any time and clear the balance early. The standard expectation is at least USD 290 before the physical training and the remaining balance within the three-month post-training period.

Q: What payment methods do you accept?
A: Depends on your country — official bank transfer, card, mobile money or an approved remittance channel. Use only details shared by Vantage Africa through the official registration page or coordinator. Always confirm the client's country before quoting a method, and ask for the payment receipt once paid.

Payment methods reference (confirm the client's country first):
- Bank transfer (all countries): Vantage Africa School of Leadership Ltd. | Equity Bank (K) Limited | Account No 1750186168502 | Swift EQBLKENA | Bank Code 068 | Branch Garden City, Branch Code 175.
- Western Union / MoneyGram / RIA (Namibia, Liberia, Malawi, Sierra Leone, Botswana, Gambia): Receiver Rachael Wambui Mwongela, Nairobi, Kenya, +254 707 315 238.
- Mobile Money (Uganda, South Africa, Rwanda, Tanzania, Zimbabwe, Kenya, South Sudan): +254 725 303 645, Benson Karanja Kiarie.
- Airtel Money (Zambia): dial *115# > Option 1 (Send Money) > Option 4 (International); +254 739 525 644, Rachael.
- Taj Bank (Nigeria): Maryfaith Vutale, Account No 0001653604.
- Ecobank (Gambia, Malawi, Rwanda, Ghana, Zimbabwe, South Africa, Uganda, Liberia, South Sudan): Benson Karanja Kiarie, Account No 6580015494, Branch Towers Kenya, Swift ECOCKENA, Bank Code 043.
- PayPal / Visa (all countries): https://tinyurl.com/2s8m89fv > Register > pay via VISA.
- Dahabshiil (South Sudan): +254 725 303 645, Benson Karanja Kiarie.

Q: I'm outside Kenya — can I still pay easily?
A: Yes — the international team provides country-appropriate options and, where applicable, a local-currency equivalent. Share your country to receive the correct official payment instructions.

Q: Can I pay in my local currency?
A: Where available, the team provides the approved amount and channel for that cohort. Because rates and arrangements change, do not convert or send money using unofficial details.

Q: Is my payment secure?
A: Only pay through the official registration page or the details supplied by Vantage Africa. Check the recipient details carefully and keep your confirmation; if anything looks different, verify with the coordinator before sending money.

Q: Group or corporate discount? (ESCALATE)
A: Vantage Africa can arrange team/institutional training and may prepare a package based on numbers, location and needs. This requires a customised quotation and scope — hand to the international/corporate training team.

Q: Scholarships or sponsorships?
A: No direct scholarship/sponsorship. However, referring five participants who register earns a free place once the five registrations are confirmed. Do not call this a "scholarship."

Q: How does the refer-five offer work?
A: No direct sponsorship, but you earn a free place by referring five participants who register. Share the referrals with the team so they can be linked to you and verified before your free attendance is confirmed.

Q: Can I receive certificates before clearing the balance?
A: No — the fee must be fully cleared, plus completing learning and practical requirements, before certificates are released. Under installments, the balance should be paid within the three-month post-training period.

Q: Is there a current discount? (ESCALATE)
A: Must be confirmed as currently active before quoting. Escalate to a human.

Q: Can I get a refund if I change my mind after paying? (ESCALATE)
A: Refund and transfer requests are handled under Vantage Africa's current refund policy and must be reviewed by a staff member. Training fees are generally non-refundable once the programme has started. The AI must not approve or calculate a refund. Policy: https://vantageafricaleaders.com/policies.php#refund

## 5. Certificate
Q: Will I get a certificate?
A: Yes — two certificates: Certified Monitoring and Evaluation, and Proposal Writing. Subject to completing the required learning/practical work and clearing the fee.

Q: Is the certificate recognised/accredited?
A: Accredited by the National Industrial Training Authority (NITA) and the Institute of Human Resource Management (IHRM). It includes a unique QR code for verification. Acceptance for a specific job/licence/study still depends on the receiving employer or institution.

Q: How can an employer verify my certificate?
A: The certificate includes a unique QR code that can be scanned to verify authenticity; an employer can use it or contact Vantage Africa for further confirmation.

Q: Is this recognised outside Africa?
A: The certificate can be verified anywhere via its QR code and the programme runs international cohorts. Recognition for a particular role/institution is decided by that employer, regulator or school, so we don't promise automatic equivalence everywhere.

Q: Do I need to attend every class to get the certificate?
A: Plan to attend all three physical days and participate in the online follow-up. You must also complete the required e-learning modules, quizzes, assignments and practical M&E deliverables and clear the fee before certification.

Q: Digital or physical certificate?
A: Certificates are issued after completion requirements are verified and include QR-code verification. Digital access is normally provided; printed-copy availability/collection is confirmed per country cohort.

Q: Why two certificates?
A: One for Certified Monitoring and Evaluation (the practical M&E programme) and one for Proposal Writing (the resource-mobilisation and proposal-writing component).

Q: Is this a diploma course?
A: No — a certificate-level, skills-based course, not a diploma.

Q: Do I need a test or exam?
A: A self-assessment quiz at the end of each module (aim 80%). Completing all e-learning lessons and assignments, attending the three physical days and participating in the online follow-up are required before the certificate is issued.

## 6. Objection / Value
Q: Is this course worth the money?
A: A fair question. The fee covers much more than three classroom days: practical training, materials, meals during the physical sessions, three months of online support and platform access, proposal-writing training, two certificates and one year of association membership. The strongest value is leaving with real M&E tools and a complete plan. It does not guarantee a job, promotion or income.

Q: How can I be sure this is worth it?
A: CMEP skills save time and money and open new career paths. Testimonials: https://youtu.be/HAWpR9gGW60?si=8EvhwajFRtW0brb7

Q: I'm new to M&E — will it be too advanced?
A: No — it starts with foundations and guides you step by step through practical tools with facilitator support, so beginners learn alongside experienced participants.

Q: What makes this different from other M&E trainings?
A: Project-based and results-oriented: you build the main M&E tools for your own case-study project and get three months of follow-up support, plus proposal writing, professional networking and continued e-learning. Purely practical, hands-on with real-world African SME/NGO/corporate case studies, a mini project and peer problem-solving.

Q: How is M&E different from a general project management course?
A: Project management focuses on planning, organising and delivering the project; M&E focuses on defining results, indicators and evidence, tracking implementation, assessing outcomes and using findings for accountability and learning. This programme develops specialist M&E tools and competencies.

Q: Will this help me get a job or promotion in the NGO/development sector?
A: It builds practical skills relevant to M&E, programme, research and project roles and gives you work products that strengthen your portfolio. It can improve readiness and confidence, but we cannot guarantee a job, promotion, contract or income.

Q: Can I get a job abroad with this?
A: The skills are universal and the certificate is internationally recognised and digitally verifiable — this can support applications in many countries, though outcomes depend on each country's local requirements.

Q: Can I use this for a career change?
A: Yes — several learners have transitioned into other careers, from completely different industries.

Q: Do you offer a free intro session before I pay?
A: Some cohorts may advertise a free info session/webinar/preview, but it's not guaranteed for every country or date. The coordinator can share any current free session or approved preview.

Q: What's your success rate?
A: No verified statistic exists — share testimonials: https://youtu.be/HAWpR9gGW60?si=8EvhwajFRtW0brb7

Q: Can I speak to a past student for reference? (ESCALATE)
A: Needs coordinator confirmation of a willing past student, for privacy. Meanwhile share the written testimonials.

Q: Can I switch to another batch if I can't attend? (ESCALATE)
A: Needs coordinator confirmation of the current deferral policy. Don't promise a free transfer without confirming.

Q: What if I stop attending halfway through? (ESCALATE)
A: Speak with the coordinator immediately so the team can advise on catch-up, transfer or completion options. Stopping may affect assignments, certification and payment obligations, and any refund/transfer decision must be handled by staff.

## 7. Course Profile Summary
- Category: EVENTS (in-person/hybrid cohort) — not virtual-only, not academic
- Full name: Certified Monitoring and Evaluation Professional (CMEP) Training – International Programme
- Format: hybrid/blended — three days of physical, hands-on training in the host country, then three months of online support (normally weekly evening sessions) + e-learning platform access
- Duration: three physical training days + three months of post-training online support; exact dates/times are cohort-specific
- Class size: 15–40; Language: English (interpretation/alt-language confirmed per cohort)
- Instructors: experienced Vantage Africa M&E facilitators; Dr. Benson Kiarie (PhD Strategic Management, MBA, B.Ed, CPA-K, CPS-K, Certified M&E Professional, Lean Six Sigma Yellow Belt, CHRP) may lead/support — cohort-specific
- Fee: USD 580 (deposit USD 80; two installments of USD 290)
- Course outline: https://drive.google.com/file/d/1iGnMBGLw_lLdazONUBW6GS8KMKyVE_47/view?usp=drive_link
- Registration: contact the programme coordinator for the current cohort registration link
- Contact: WhatsApp/call +254 102 179 174 · email john@vantageafricaleaders.com

## 8. Tone & Do-Not-Say Rules
Greet warmly by first name where available; sign off as "The Vantage Team" or the named coordinator.
Always spell correctly: Vantage Africa School of Leadership; Certified Monitoring and Evaluation Professional (CMEP); Monitoring and Evaluation (M&E); NITA; IHRM; Vantage Africa M&E Professionals Association.
Never say / never promise: guaranteed employment/promotion/income/consultancy; visa/travel/accommodation support; automatic recognition by every employer/regulator; a refund outside official policy; a full recording of a physical training session; lifetime or unlimited access to materials; calling the referral offer a "scholarship"; and never give any answer from this document for a virtual or academic programme.

## 9. Escalation Rules
The AI must NOT answer/resolve alone (hand off to a human):
- Any inquiry about a virtual, online-only, or academic programme
- Refund or cancellation requests; complaints or disputes
- Payment not reflecting / unconfirmed
- Custom corporate or group quotations; negotiated prices
- Referral-credit disputes; cohort transfers involving an existing payment
- Certification withheld or delayed; legal/policy questions; visa/travel commitments
- Any request outside the standard USD 580 package
- Current discount/promo confirmation; next intake date confirmation; past-student reference requests
Hand off to a human immediately when: the inquiry is not about an events cohort; money has been sent but not confirmed; a participant requests a refund or transfer; a complaint is raised; a certificate is delayed/withheld; five-referral eligibility is disputed; an organisation requests customised/corporate training.
MNEKB;
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-journal-text me-2"></i>AI Knowledge Base</h4>
                    <p class="text-muted mb-0">Per-course/event info the AI answers from (fees, schedule, requirements, FAQs)</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wa_settings.php" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i>Settings</a>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div>
            <?php endif; ?>

            <?php
            $outlineScannedAt = wa_setting_get($conn, 'event_outline_text_at', '');
            $outlineHasText   = wa_event_outline_text($conn) !== '';
            ?>
            <div class="border rounded p-3 mb-3 bg-light d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto">
                    <span class="fw-semibold"><i class="bi bi-calendar3 me-1"></i>Event schedule (course outline)</span>
                    <div class="small text-muted">
                        <?php if ($outlineHasText): ?>
                            Scanned <?php echo wa_e($outlineScannedAt); ?> — the AI answers schedule/agenda questions from the outline.
                        <?php else: ?>
                            Scan the shared course-outline PDF so the AI can answer schedule/agenda questions (daily programme, session times, topics).
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" id="outlineScan" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i><?php echo $outlineHasText ? 'Re-scan outline' : 'Scan outline schedule'; ?></button>
                <span id="outlineScanMsg" class="small text-muted"></span>
            </div>
            <script>
            (function () {
                var b = document.getElementById('outlineScan'); if (!b) return;
                var msg = document.getElementById('outlineScanMsg');
                b.addEventListener('click', function () {
                    b.disabled = true; msg.textContent = 'Scanning the outline PDF… (~20s)'; msg.className = 'small text-muted';
                    var fd = new FormData(); fd.append('action', 'outline_import');
                    fetch('includes/wa_api.php', { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            b.disabled = false;
                            if (d && d.ok) { msg.textContent = 'Scanned — the AI now answers schedule questions from the outline.'; msg.className = 'small text-success'; }
                            else {
                                var m = { forbidden:'Supervisors only.', no_provider:'No AI key.', needs_claude:'Set the AI provider to Claude (PDF reading) in Settings.', no_pdf:'Could not download the outline PDF — check it is shared "anyone with the link".', too_large:'Outline PDF too large.', ai_failed:'AI request failed.', empty:'No text extracted.' }[d && d.error] || (d && d.error) || 'error';
                                msg.textContent = 'Could not scan: ' + m; msg.className = 'small text-danger';
                            }
                        })
                        .catch(function () { b.disabled = false; msg.textContent = 'Request failed.'; msg.className = 'small text-danger'; });
                });
            })();
            </script>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-uppercase">Course / Event / Programme knowledge</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3" style="max-width:520px">
                        <label class="form-label small text-muted">Choose a course, event or training programme</label>
                        <select class="form-select" onchange="if(this.value) location.href='wa_knowledge.php?ref='+this.value;">
                            <option value="">— Select —</option>
                            <?php if ($programs): ?>
                            <optgroup label="Training programmes">
                                <?php foreach ($programs as $p): $v = 'program:' . (int)$p['id']; ?>
                                    <option value="<?php echo $v; ?>" <?php echo ($selType === 'program' && $selId === (int)$p['id']) ? 'selected' : ''; ?>>
                                        <?php echo wa_e($p['name']); ?><?php echo ((int)$p['status'] !== 1) ? ' (inactive)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <optgroup label="Courses">
                                <?php foreach ($courses as $c): $v = 'course:' . (int)$c['id']; ?>
                                    <option value="<?php echo $v; ?>" <?php echo ($selType === 'course' && $selId === (int)$c['id']) ? 'selected' : ''; ?>>
                                        <?php echo wa_e($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php if ($events): ?>
                            <optgroup label="Events">
                                <?php foreach ($events as $ev): $v = 'event:' . (int)$ev['id']; ?>
                                    <option value="<?php echo $v; ?>" <?php echo ($selType === 'event' && $selId === (int)$ev['id']) ? 'selected' : ''; ?>>
                                        <?php echo wa_e($ev['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Manage training programmes (themes matched to events by keyword) -->
                    <div class="border rounded p-3 mb-3 bg-light" style="max-width:720px">
                        <div class="fw-semibold mb-1"><i class="bi bi-collection me-1"></i>Training programmes</div>
                        <p class="small text-muted mb-2">Themes like <em>M&amp;E</em>, <em>Data Analysis</em>, <em>Academic Programs</em>. Give each a name and comma-separated keywords that appear in your Event titles — the bot pulls each programme's country/dates live from the Events table and answers from the programme's knowledge base.</p>
                        <?php foreach ($programs as $p): $matched = wa_program_events($conn, $p); ?>
                            <div class="d-flex justify-content-between align-items-center bg-white border rounded p-2 mb-1">
                                <div>
                                    <a href="wa_knowledge.php?ref=program:<?php echo (int)$p['id']; ?>" class="fw-semibold text-decoration-none"><?php echo wa_e($p['name']); ?></a>
                                    <?php if ((int)$p['status'] !== 1): ?><span class="badge bg-secondary ms-1">inactive</span><?php endif; ?>
                                    <div class="small text-muted">Keywords: <?php echo wa_e($p['keywords'] ?: '(name)'); ?> · <?php echo count($matched); ?> upcoming session<?php echo count($matched) === 1 ? '' : 's'; ?></div>
                                </div>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="waEditProg(<?php echo (int)$p['id']; ?>, <?php echo htmlspecialchars(json_encode($p['name']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($p['keywords'] ?: ''), ENT_QUOTES); ?>, <?php echo (int)$p['status']; ?>)">Edit</button>
                                    <form method="post" action="includes/wa_process.php" class="m-0" onsubmit="return confirm('Delete this programme and its knowledge base?');">
                                        <input type="hidden" name="action" value="delete_program">
                                        <input type="hidden" name="program_id" value="<?php echo (int)$p['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <form method="post" action="includes/wa_process.php" class="row g-2 mt-1">
                            <input type="hidden" name="action" value="save_program">
                            <input type="hidden" name="program_id" id="progId" value="0">
                            <div class="col-12 col-md-4"><input type="text" name="name" id="progName" class="form-control form-control-sm" placeholder="Programme name (e.g. M&E)" required></div>
                            <div class="col-12 col-md-5"><input type="text" name="keywords" id="progKw" class="form-control form-control-sm" placeholder="keywords: monitoring, evaluation, M&E"></div>
                            <div class="col-6 col-md-2">
                                <select name="status" id="progStatus" class="form-select form-select-sm"><option value="1">Active</option><option value="0">Inactive</option></select>
                            </div>
                            <div class="col-6 col-md-1"><button type="submit" class="btn btn-sm btn-primary w-100">Save</button></div>
                        </form>
                    </div>
                    <script>
                    function waEditProg(id, name, kw, status) {
                        document.getElementById('progId').value = id;
                        document.getElementById('progName').value = name;
                        document.getElementById('progKw').value = kw;
                        document.getElementById('progStatus').value = String(status);
                        document.getElementById('progName').focus();
                    }
                    </script>

                    <?php if ($selType && $selId): ?>
                        <?php if ($selType === 'event'): ?>
                        <!-- Build an event's knowledge from a training programme + its details -->
                        <div class="border rounded p-3 mb-3" style="background:#eef7ff; border-color:#b6dcff !important">
                            <div class="fw-semibold mb-1"><i class="bi bi-buildings me-1"></i>Build this event's knowledge</div>
                            <p class="small text-muted mb-2">
                                Details pulled from the system for <strong><?php echo wa_e($selName); ?></strong><?php echo $evDates !== '' ? ' (' . wa_e($evDates) . ')' : ''; ?>: name, dates<?php echo $evCost !== '' ? ', price' : ''; ?><?php echo $evReg !== '' ? ', registration link' : ''; ?>. Pick the training programme (its general database), review the fields and <strong>add anything missing (e.g. the hotel/venue)</strong>, then Build — the AI writes the event KB from these plus the programme info and the shared course outline.
                            </p>
                            <?php if (!$programs): ?>
                                <div class="alert alert-warning py-1 px-2 small mb-2">No training programmes defined yet. Create <strong>M&amp;E</strong> / <strong>Data Analysis</strong> as programmes first (pick a programme from the dropdown at the top), then come back.</div>
                            <?php endif; ?>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">Training programme (general database)</label>
                                    <select id="evProgram" class="form-select form-select-sm">
                                        <option value="0">— None —</option>
                                        <?php foreach ($programs as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>" <?php echo ($evProgramId === (int)$p['id']) ? 'selected' : ''; ?>><?php echo wa_e($p['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4"><label class="form-label small mb-0">City / country</label><input type="text" id="evCity" class="form-control form-control-sm" value="<?php echo wa_e($evCity); ?>" placeholder="e.g. Mbabane, Eswatini"></div>
                                <div class="col-md-4"><label class="form-label small mb-0">Hotel / venue <span class="text-danger">(add this)</span></label><input type="text" id="evHotel" class="form-control form-control-sm" placeholder="e.g. Happy Valley Hotel"></div>
                                <div class="col-md-4"><label class="form-label small mb-0">Cost / fee</label><input type="text" id="evCost" class="form-control form-control-sm" value="<?php echo wa_e($evCost); ?>" placeholder="e.g. USD 950"></div>
                                <div class="col-md-8"><label class="form-label small mb-0">Registration link</label><input type="text" id="evReg" class="form-control form-control-sm" value="<?php echo wa_e($evReg); ?>" placeholder="https://…"></div>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <button type="button" id="evBuild" class="btn btn-primary btn-sm"><i class="bi bi-magic me-1"></i>Build event knowledge</button>
                                <span id="evBuildMsg" class="small text-muted"></span>
                            </div>
                        </div>
                        <script>
                        (function () {
                            var b = document.getElementById('evBuild'); if (!b) return;
                            b.addEventListener('click', function () {
                                var msg = document.getElementById('evBuildMsg'), box = document.getElementById('kbBody');
                                b.disabled = true; msg.textContent = 'Building… (~15s)'; msg.className = 'small text-muted';
                                var fd = new FormData();
                                fd.append('action', 'event_build');
                                fd.append('ref_type', 'event');
                                fd.append('ref_id', '<?php echo (int)$selId; ?>');
                                fd.append('program_id', document.getElementById('evProgram').value);
                                fd.append('city', document.getElementById('evCity').value);
                                fd.append('hotel', document.getElementById('evHotel').value);
                                fd.append('cost', document.getElementById('evCost').value);
                                fd.append('reglink', document.getElementById('evReg').value);
                                fetch('includes/wa_api.php', { method: 'POST', body: fd })
                                    .then(function (r) { return r.json(); })
                                    .then(function (d) {
                                        b.disabled = false;
                                        if (d && d.ok) {
                                            if (box) { box.value = d.text; }
                                            if (typeof window.kbRenderSections === 'function') window.kbRenderSections();
                                            msg.textContent = 'Built & saved.'; msg.className = 'small text-success';
                                        } else {
                                            var m = { forbidden:'Supervisors only.', no_event:'Pick an event first.', unknown_event:'Unknown event.', ai_failed:'AI request failed.', empty_result:'AI returned nothing.', no_provider:'No AI key.' }[d && d.error] || (d && d.error) || 'error';
                                            msg.textContent = 'Could not build: ' + m; msg.className = 'small text-danger';
                                        }
                                    })
                                    .catch(function () { b.disabled = false; msg.textContent = 'Request failed.'; msg.className = 'small text-danger'; });
                            });
                        })();
                        </script>
                        <?php endif; ?>

                        <!-- AI draft generator -->
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="fw-semibold mb-1"><i class="bi bi-magic me-1"></i>Generate a draft from your website</div>
                            <?php if ($providerReady): ?>
                                <p class="small text-muted mb-2">Leave the box blank to auto-search your site, or paste the exact course page URL(s), one per line.</p>
                                <textarea id="kbUrls" class="form-control mb-2" rows="2"
                                          placeholder="https://vantageafricaleaders.com/... (optional)"></textarea>
                                <button type="button" id="kbGen" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-magic me-1"></i>Generate with AI
                                </button>
                                <span id="kbGenStatus" class="small text-muted ms-2"></span>
                            <?php else: ?>
                                <p class="small text-warning mb-0">Set an AI key on the <a href="wa_settings.php">Settings</a> page to enable draft generation.</p>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label mb-0">
                                Knowledge for <strong><?php echo wa_e($selName); ?></strong>
                            </label>
                            <div class="d-flex gap-2">
                                <button type="button" id="kbMneEvents" class="btn btn-sm btn-outline-primary" title="Fill with the ready-made M&E events knowledge — review, then Save">
                                    <i class="bi bi-mortarboard me-1"></i>Load M&amp;E events knowledge
                                </button>
                                <button type="button" id="kbTemplate" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-list-check me-1"></i>Insert 8-section template
                                </button>
                            </div>
                        </div>
                        <script>
                        (function () {
                            var b = document.getElementById('kbMneEvents'); if (!b) return;
                            var MNE_KB = <?php echo json_encode($mneEventsKb); ?>;
                            b.addEventListener('click', function () {
                                var box = document.getElementById('kbBody');
                                if (box.value.trim() !== '' && !confirm('Replace the current text with the M&E events knowledge base? Review and Save after.')) return;
                                box.value = MNE_KB; box.focus();
                            });
                        })();
                        </script>
                        <textarea id="kbBody" class="form-control mt-2" rows="20" style="font-family:inherit"><?php echo wa_e($kbBody); ?></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">This is what the AI answers from — edit it directly and click <strong>Save</strong>. Sections 7 (Escalation) &amp; 8 (Do-Not-Say) are rules the AI follows and never reads aloud.</small>
                            <button type="button" id="kbSave" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save</button>
                        </div>
                        <div id="kbSaveMsg" class="small mt-1"></div>

                        <?php if ($learnings): ?>
                        <!-- Learn from the team: human replies pending review -->
                        <div class="border rounded p-3 mt-3" style="background:#fff8e1; border-color:#ffe08a !important">
                            <div class="fw-semibold mb-1">
                                <i class="bi bi-mortarboard me-1"></i>Learn from the team
                                <span class="badge bg-warning text-dark ms-1"><?php echo count($learnings); ?></span>
                            </div>
                            <p class="small text-muted mb-3">Things staff told clients about <strong><?php echo wa_e($selName); ?></strong> in chat. <strong>Approve</strong> to fold into the knowledge base (the AI re-reads it), or <strong>Dismiss</strong>.</p>
                            <?php foreach ($learnings as $l): ?>
                                <div class="bg-white border rounded p-2 mb-2">
                                    <div style="white-space:pre-wrap; word-wrap:break-word"><?php echo wa_e($l['body']); ?></div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">
                                            <?php echo wa_e($l['author'] ?: 'Staff'); ?> · <?php echo wa_e($l['created_at']); ?>
                                        </small>
                                        <div class="d-flex gap-1">
                                            <form method="post" action="includes/wa_process.php" class="m-0">
                                                <input type="hidden" name="action" value="learn_approve">
                                                <input type="hidden" name="learning_id" value="<?php echo (int)$l['id']; ?>">
                                                <input type="hidden" name="ref_type" value="<?php echo wa_e($selType); ?>">
                                                <input type="hidden" name="ref_id" value="<?php echo (int)$selId; ?>">
                                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                                            </form>
                                            <form method="post" action="includes/wa_process.php" class="m-0">
                                                <input type="hidden" name="action" value="learn_dismiss">
                                                <input type="hidden" name="learning_id" value="<?php echo (int)$l['id']; ?>">
                                                <input type="hidden" name="ref_type" value="<?php echo wa_e($selType); ?>">
                                                <input type="hidden" name="ref_id" value="<?php echo (int)$selId; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Dismiss</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (trim($kbAi) !== '' && trim($kbAi) !== trim($kbBody)): ?>
                        <!-- What the AI actually reads: processed bullets -->
                        <div class="border rounded p-3 mt-3 bg-light">
                            <div class="fw-semibold mb-1"><i class="bi bi-stars me-1"></i>What the AI reads (auto-processed)</div>
                            <p class="small text-muted mb-2">Regenerated from your text each time you Save. This cleaned-up version is what the live AI answers from.</p>
                            <div class="bg-white border rounded p-2" style="white-space:pre-wrap; word-wrap:break-word; max-height:320px; overflow-y:auto"><?php echo wa_e($kbAi); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($providerReady): ?>
                        <!-- Test the AI against this knowledge (no message is sent) -->
                        <div class="border rounded p-3 mt-3 bg-light">
                            <div class="fw-semibold mb-1"><i class="bi bi-robot me-1"></i>Test the AI</div>
                            <p class="small text-muted mb-2">Ask a question the way a prospect would. The AI answers from the text above (including unsaved edits). Nothing is sent.</p>
                            <div class="d-flex gap-2">
                                <input type="text" id="kbTestQ" class="form-control" placeholder="e.g. How much is the fee and can I pay in installments?">
                                <button type="button" id="kbTestBtn" class="btn btn-outline-primary"><i class="bi bi-send me-1"></i>Test</button>
                            </div>
                            <div id="kbTestOut" class="mt-2 d-none">
                                <div class="bg-white border rounded p-2" style="white-space:pre-wrap; word-wrap:break-word" id="kbTestReply"></div>
                                <div class="small mt-1" id="kbTestMeta"></div>
                            </div>
                        </div>

                        <script>
                        (function () {
                            var tb = document.getElementById('kbTestBtn');
                            if (!tb) return;
                            tb.addEventListener('click', function () {
                                var q = document.getElementById('kbTestQ').value.trim();
                                var out = document.getElementById('kbTestOut');
                                var reply = document.getElementById('kbTestReply');
                                var meta = document.getElementById('kbTestMeta');
                                if (!q) { return; }
                                var fd = new FormData();
                                fd.append('action', 'kb_test');
                                fd.append('ref_type', '<?php echo wa_e($selType); ?>');
                                fd.append('ref_id', '<?php echo (int)$selId; ?>');
                                fd.append('q', q);
                                fd.append('kb', document.getElementById('kbBody').value);
                                tb.disabled = true; out.classList.remove('d-none');
                                reply.textContent = ''; meta.textContent = 'Thinking…';
                                fetch('includes/wa_api.php', { method: 'POST', body: fd })
                                    .then(function (r) { return r.json(); })
                                    .then(function (d) {
                                        tb.disabled = false;
                                        if (d && d.ok) {
                                            reply.textContent = d.reply || '(no reply)';
                                            meta.innerHTML = d.escalate
                                                ? '<span class="badge bg-warning text-dark">Would escalate to a human</span>'
                                                : '<span class="badge bg-success">Would answer automatically</span>';
                                            if (!d.has_kb) meta.innerHTML += ' <span class="text-muted">· no knowledge text yet</span>';
                                        } else {
                                            var m = { no_provider: 'No AI key configured (Settings).', no_question: 'Type a question first.', ai_failed: 'The AI request failed.' }[d && d.error] || (d && d.error) || 'error';
                                            reply.textContent = ''; meta.textContent = 'Could not test: ' + m;
                                        }
                                    })
                                    .catch(function () { tb.disabled = false; meta.textContent = 'Request failed.'; });
                            });
                        })();
                        </script>

                        <script>
                        (function () {
                            var btn = document.getElementById('kbGen');
                            if (!btn) return;
                            btn.addEventListener('click', function () {
                                var st = document.getElementById('kbGenStatus');
                                var body = document.getElementById('kbBody');
                                var fd = new FormData();
                                fd.append('action', 'kb_generate');
                                fd.append('ref_type', '<?php echo wa_e($selType); ?>');
                                fd.append('ref_id', '<?php echo (int)$selId; ?>');
                                fd.append('urls', document.getElementById('kbUrls').value);
                                btn.disabled = true;
                                st.textContent = 'Searching your website & drafting… (this can take ~10s)';
                                fetch('includes/wa_api.php', { method: 'POST', body: fd })
                                    .then(function (r) { return r.json(); })
                                    .then(function (d) {
                                        btn.disabled = false;
                                        if (d && d.ok) {
                                            body.value = d.text;
                                            var src = (d.sources && d.sources.length ? ' (from ' + d.sources.length + ' page' + (d.sources.length > 1 ? 's' : '') + ')' : '');
                                            window.waKbSave(body.value, function (ok) {
                                                st.textContent = ok ? ('Draft generated & saved' + src + '.') : ('Draft ready' + src + ' — saving failed, try Generate again.');
                                            });
                                        } else {
                                            var msg = { no_content: 'Could not read any page — paste the course URL directly.', no_provider: 'No AI key configured.', ai_failed: 'The AI request failed.' }[d && d.error] || (d && d.error) || 'error';
                                            st.textContent = 'Could not generate: ' + msg;
                                        }
                                    })
                                    .catch(function () { btn.disabled = false; st.textContent = 'Request failed.'; });
                            });
                        })();
                        </script>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-muted py-4 text-center">
                            <i class="bi bi-arrow-up-circle fs-3 d-block mb-2"></i>
                            Pick a course or event above to add its knowledge.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var btn = document.getElementById('kbTemplate');
    if (!btn) return;
    var TEMPLATE = [
        '=== 1. COURSE BASICS ===',
        'Full name (and nicknames): ',
        'One-line description: ',
        'One-line OUTCOME hook (the concrete result they walk away with — the AI leads with this): ',
        'Who it\'s for: ',
        'Format (online/onsite/hybrid): ',
        'Duration & schedule (start dates, session times, length): ',
        'Language of instruction: ',
        '',
        '=== 2. PRICING & PAYMENT ===',
        'Full fee (and currency): ',
        'Deposit to secure a place (amount): ',
        'Installment terms (balance paid in installments, completed before the course ends): ',
        'Discounts / scholarships / early-bird / group rates: ',
        'Payment methods (M-Pesa, bank, card): ',
        'Refund policy: ',
        'Fee includes / excludes: ',
        '',
        '=== 3. ENROLLMENT & LOGISTICS ===',
        'Registration link (the exact URL the bot shares when someone wants to register): ',
        'How to register (exact steps): ',
        'Documents / prerequisites: ',
        'Application deadline / rolling admission: ',
        'What happens after payment: ',
        'For onsite — venue, address, directions, parking, what to bring: ',
        '',
        '=== 4. CONTENT & OUTCOMES ===',
        'Topics / modules covered: ',
        'What a participant achieves by the end: ',
        'Certificate / accreditation (recognised by whom?): ',
        'Instructor(s) and credentials: ',
        'Sample materials / syllabus link: ',
        '',
        '=== 5. FREQUENTLY ASKED QUESTIONS (aim for 15-20) ===',
        'Q: ',
        'A: ',
        '',
        'Q: ',
        'A: ',
        '',
        '=== 6. OBJECTIONS & HESITATIONS ===',
        'Too expensive -> ',
        'Not sure it\'s relevant to me -> ',
        'No time -> ',
        'Need to ask my employer/spouse -> ',
        '',
        '=== 7. ESCALATION (rules for the AI — never shown to the prospect) ===',
        'Questions the AI must NOT answer / must escalate: ',
        'Hand off to a human immediately when: (complaints, refund disputes, custom corporate deals, ...) ',
        'Sensitive / easily-misstated topics: ',
        '',
        '=== 8. TONE & DO-NOT-SAY (rules for the AI) ===',
        'Preferred greeting & sign-off: ',
        'Always-use words / correct brand & course names: ',
        'Never say / never promise (guarantees, medical/legal claims, competitor comparisons): ',
        ''
    ].join('\n');
    btn.addEventListener('click', function () {
        var box = document.getElementById('kbBody');
        if (box.value.trim() !== '' && !confirm('Replace the current text with the blank 8-section template?')) return;
        box.value = TEMPLATE;
        box.focus();
    });
})();
</script>

<script>
// Save the knowledge base via AJAX (direct editing). Also used by the website-generate bootstrap.
window.waKbSave = function (val, done) {
    var fd = new FormData();
    fd.append('action', 'kb_save');
    fd.append('ref_type', '<?php echo wa_e($selType); ?>');
    fd.append('ref_id', '<?php echo (int)$selId; ?>');
    fd.append('body', val);
    fetch('includes/wa_api.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (s) { if (done) done(s && s.ok); })
        .catch(function () { if (done) done(false); });
};
(function () {
    var btn = document.getElementById('kbSave'); if (!btn) return;
    var box = document.getElementById('kbBody');
    var msg = document.getElementById('kbSaveMsg');
    btn.addEventListener('click', function () {
        btn.disabled = true; msg.textContent = 'Saving…'; msg.className = 'small text-muted mt-1';
        window.waKbSave(box.value, function (ok) {
            btn.disabled = false;
            msg.textContent = ok ? 'Saved.' : 'Could not save — try again.';
            msg.className = 'small mt-1 ' + (ok ? 'text-success' : 'text-danger');
        });
    });
})();
</script>

<?php require_once 'footer.php'; ?>
