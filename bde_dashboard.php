<?php
// admin/bde_dashboard.php
// Private BDE performance dashboard — phase 1 (illustrative / dummy data).
// Reached by direct URL only; NOT yet linked in the shared CRM navigation, so
// nobody else's view changes. When we promote it we add one role-gated link.
// Locked to a single BDE context (BDE / Virtual / Dorcas Mukami Murithi); the
// Role/Department/Employee selectors are display-only for now.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vantage Africa — BDE Command Centre</title>
  <style>
    :root{
      --bg:#f3f6f8; --surface:#ffffff; --surface2:#f8fafb; --ink:#15212b; --muted:#677582;
      --line:#dde5ea; --navy:#153f5d; --navy2:#0f314a; --orange:#e46f24; --teal:#218c91;
      --green:#238553; --amber:#b27a05; --red:#bd3e3e; --blue:#3478b8; --purple:#7259ad;
      --greenbg:#eaf7ef; --amberbg:#fff6dd; --redbg:#fdecec; --bluebg:#eaf3fb; --shadow:0 10px 28px rgba(26,51,68,.08);
    }
    html[data-theme="dark"]{
      --bg:#111820; --surface:#18222c; --surface2:#202c37; --ink:#eef4f7; --muted:#9fb0bd;
      --line:#2f3e49; --navy:#79b9df; --navy2:#9fd2ee; --orange:#f28a4c; --teal:#55bfc0;
      --green:#62c489; --amber:#e2b456; --red:#ee7777; --blue:#73aee4; --purple:#a996dd;
      --greenbg:#183125; --amberbg:#322a17; --redbg:#351f22; --bluebg:#192c3d; --shadow:none;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;line-height:1.45}
    button,select,input,textarea{font:inherit;color:inherit}
    button{cursor:pointer}
    .app{max-width:1700px;margin:0 auto;padding:18px}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:15px 18px;box-shadow:var(--shadow);position:sticky;top:10px;z-index:20}
    .brand{display:flex;align-items:center;gap:12px;min-width:260px}
    .mark{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--navy),var(--orange));display:grid;place-items:center;color:#fff;font-weight:900;letter-spacing:-1px}
    .brand h1{font-size:17px;margin:0;line-height:1.15}
    .brand p{margin:2px 0 0;color:var(--muted);font-size:12px}
    .controls{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;align-items:end}
    .control{display:grid;gap:4px}
    .control label{font-size:10px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:800}
    select,.input,textarea{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:9px 10px;outline:none}
    select:focus,.input:focus,textarea:focus{border-color:var(--orange);box-shadow:0 0 0 3px color-mix(in srgb,var(--orange) 18%,transparent)}
    .icon-btn,.primary-btn,.ghost-btn{border:1px solid var(--line);border-radius:10px;padding:9px 12px;background:var(--surface2);font-weight:750}
    .primary-btn{background:var(--navy);color:#fff;border-color:var(--navy)}
    .ghost-btn:hover,.icon-btn:hover{border-color:var(--orange)}
    /* Read-only display for the locked BDE context */
    .readonly-field{background:var(--surface2);border:1px dashed var(--line);border-radius:10px;padding:9px 10px;font-weight:750;min-width:150px;white-space:nowrap}
    .readonly-field small{display:block;font-size:9px;color:var(--muted);font-weight:800;letter-spacing:.05em;text-transform:uppercase;margin-top:1px}
    .strategy-strip{margin-top:14px;border-radius:16px;background:linear-gradient(110deg,var(--navy2),var(--navy));color:#fff;padding:16px 18px;display:grid;grid-template-columns:minmax(0,1.4fr) minmax(250px,.8fr);gap:16px;align-items:center}
    .strategy-strip .eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:.16em;opacity:.75;font-weight:800}
    .strategy-strip h2{font-size:20px;margin:3px 0 3px}
    .strategy-strip p{margin:0;opacity:.86;font-size:13px;max-width:95ch}
    .strategy-focus{background:rgba(255,255,255,.11);border:1px solid rgba(255,255,255,.18);border-radius:13px;padding:11px 13px}
    .strategy-focus b{display:block;color:#ffd6bf;margin-bottom:2px}

    /* Left-nav shell: sidebar (was the top tab row) + workspace */
    .shell{display:grid;grid-template-columns:236px minmax(0,1fr);gap:14px;margin-top:14px;align-items:start}
    .sidebar{position:sticky;top:86px;background:var(--surface);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:12px}
    .sidebar .nav-label{font-size:10px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:800;padding:2px 8px 8px}
    .sidenav{display:grid;gap:6px}
    .sidebar .tab{width:100%;text-align:left;border:1px solid transparent;background:transparent;border-radius:10px;padding:11px 12px;font-weight:750;color:var(--muted)}
    .sidebar .tab:hover{background:var(--surface2);color:var(--ink)}
    .sidebar .tab.active{background:var(--orange);border-color:var(--orange);color:#fff}

    .workspace{display:grid;gap:14px}
    .hero{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(320px,.55fr);gap:14px}
    .panel,.metric,.action-card{background:var(--surface);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}
    .panel{padding:16px}
    .panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px}
    .panel-head h3{font-size:15px;margin:0}
    .panel-head p{font-size:12px;color:var(--muted);margin:3px 0 0}
    .badge{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:850;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap}
    .badge.green{color:var(--green);background:var(--greenbg)}
    .badge.amber{color:var(--amber);background:var(--amberbg)}
    .badge.red{color:var(--red);background:var(--redbg)}
    .badge.blue{color:var(--blue);background:var(--bluebg)}
    .metric-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
    .metric{padding:13px;min-height:112px;position:relative;overflow:hidden}
    .metric::after{content:"";position:absolute;width:78px;height:78px;border-radius:50%;right:-34px;top:-34px;background:color-mix(in srgb,var(--orange) 10%,transparent)}
    .metric .label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);font-weight:800}
    .metric .value{font-size:23px;font-weight:850;margin:8px 0 4px;line-height:1.1;letter-spacing:-.02em}
    .metric .note{font-size:11px;color:var(--muted)}
    .metric .delta{font-size:11px;font-weight:750;margin-top:6px}
    .up{color:var(--green)} .down{color:var(--red)} .neutral{color:var(--amber)}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
    .progress-wrap{margin-top:8px}
    .progress-label{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:5px}
    .track{height:10px;background:var(--surface2);border-radius:99px;overflow:hidden;border:1px solid var(--line)}
    .fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--teal),var(--orange));width:0;transition:width .35s ease}
    .commission-road{position:relative;padding:14px 5px 4px;margin-top:4px}
    .road{height:12px;border-radius:99px;background:var(--surface2);border:1px solid var(--line);overflow:hidden}
    .road .fill{background:linear-gradient(90deg,var(--red),var(--amber),var(--green));max-width:100%}
    .marker{position:absolute;top:7px;transform:translateX(-50%);text-align:center}
    .marker::before{content:"";display:block;width:2px;height:20px;background:var(--muted);margin:auto;opacity:.55}
    .marker span{font-size:9px;color:var(--muted);font-weight:800}
    .marker.m80{left:66.67%}.marker.m100{left:83.33%}.marker.m120{left:100%}
    .commission-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:22px}
    .mini{background:var(--surface2);border:1px solid var(--line);border-radius:11px;padding:10px}
    .mini b{font-size:16px;display:block;margin-top:3px}
    .mini span{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-weight:800}
    .list{display:grid;gap:8px}
    .action-card{padding:11px 12px;display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:start;box-shadow:none}
    .priority-dot{width:10px;height:10px;border-radius:50%;margin-top:5px}
    .priority-dot.red{background:var(--red)}.priority-dot.amber{background:var(--amber)}.priority-dot.green{background:var(--green)}.priority-dot.blue{background:var(--blue)}
    .action-card b{font-size:12.5px}
    .action-card p{margin:2px 0 0;font-size:11.5px;color:var(--muted)}
    .due{font-size:10px;font-weight:800;color:var(--muted);white-space:nowrap}
    .driver-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:9px}
    .driver{border:1px solid var(--line);border-radius:12px;background:var(--surface2);padding:11px}
    .driver .top{display:flex;justify-content:space-between;gap:6px;align-items:center}
    .driver b{font-size:12px}
    .driver .num{font-size:18px;font-weight:850;margin:8px 0 2px}
    .driver small{color:var(--muted)}
    .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:13px}
    table{width:100%;border-collapse:collapse;min-width:760px;background:var(--surface)}
    th,td{text-align:left;padding:10px 11px;border-bottom:1px solid var(--line);font-size:11.5px;vertical-align:middle}
    th{font-size:9.5px;letter-spacing:.09em;text-transform:uppercase;color:var(--muted);background:var(--surface2);position:sticky;top:0}
    tr:last-child td{border-bottom:0}
    .person{display:flex;align-items:center;gap:9px}
    .avatar{width:30px;height:30px;border-radius:9px;background:var(--navy);color:#fff;display:grid;place-items:center;font-size:10px;font-weight:850}
    .person b{display:block;font-size:11.5px}
    .person span{font-size:10px;color:var(--muted)}
    .score{font-weight:850}
    .funnel{display:grid;gap:9px}
    .funnel-row{display:grid;grid-template-columns:145px 1fr 65px;gap:10px;align-items:center}
    .funnel-row label{font-size:11.5px;font-weight:700}
    .funnel-bar{height:24px;background:var(--surface2);border:1px solid var(--line);border-radius:7px;overflow:hidden}
    .funnel-bar div{height:100%;background:linear-gradient(90deg,var(--navy),var(--teal));display:flex;align-items:center;padding-left:8px;color:#fff;font-size:10px;font-weight:800}
    .funnel-row .conversion{font-size:10px;color:var(--muted);text-align:right}
    .source-row{display:grid;grid-template-columns:130px 1fr 70px;gap:9px;align-items:center;margin:8px 0}
    .source-row .bar{height:9px;border-radius:99px;background:var(--surface2);overflow:hidden;border:1px solid var(--line)}
    .source-row .bar div{height:100%;background:var(--orange)}
    .chart{width:100%;height:190px;display:block}
    .chart text{fill:var(--muted);font-size:10px}
    .chart .grid{stroke:var(--line);stroke-width:1}
    .chart .line{fill:none;stroke:var(--orange);stroke-width:3}
    .chart .area{fill:color-mix(in srgb,var(--orange) 13%,transparent)}
    .chart .dot{fill:var(--surface);stroke:var(--orange);stroke-width:2}
    .scenario{display:grid;grid-template-columns:repeat(5,1fr) auto;gap:9px;align-items:end}
    .scenario .control label{font-size:9px}
    .scenario input{width:100%;min-width:0}
    .callout{background:var(--bluebg);border:1px solid color-mix(in srgb,var(--blue) 35%,var(--line));border-radius:13px;padding:12px;font-size:12px}
    .callout strong{color:var(--blue)}
    .principles{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    .principle{border-left:4px solid var(--orange);background:var(--surface2);border-radius:8px;padding:11px}
    .principle b{font-size:12px}
    .principle p{font-size:11px;color:var(--muted);margin:4px 0 0}
    .timeline{display:grid;gap:8px}
    .time-row{display:grid;grid-template-columns:110px 1fr;gap:12px;padding:9px 0;border-bottom:1px solid var(--line)}
    .time-row:last-child{border-bottom:0}
    .time-row time{font-size:11px;font-weight:850;color:var(--orange)}
    .time-row div{font-size:11.5px}
    .form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
    .field{display:grid;gap:4px}
    .field label{font-size:9px;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);font-weight:800}
    .field.span2{grid-column:span 2}.field.span4{grid-column:span 4}
    textarea{min-height:78px;resize:vertical}
    .report-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
    .report-preview{white-space:pre-wrap;background:var(--surface2);border:1px dashed var(--line);border-radius:12px;padding:13px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;min-height:130px}
    .checklist{display:grid;gap:7px}
    .check{display:grid;grid-template-columns:auto 1fr auto;gap:9px;align-items:center;border:1px solid var(--line);border-radius:10px;padding:9px;background:var(--surface2)}
    .check .symbol{width:20px;height:20px;border-radius:50%;display:grid;place-items:center;font-size:11px;font-weight:900}
    .check.pass .symbol{background:var(--greenbg);color:var(--green)}
    .check.fail .symbol{background:var(--redbg);color:var(--red)}
    .check b{font-size:11.5px}.check span{font-size:10px;color:var(--muted)}
    .dev-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
    code{background:var(--surface2);border:1px solid var(--line);border-radius:5px;padding:1px 5px;font-size:11px}
    .footer-note{font-size:11px;color:var(--muted);text-align:center;padding:18px 8px 5px}
    .hidden{display:none!important}
    @media(max-width:1250px){
      .metric-grid{grid-template-columns:repeat(3,1fr)}
      .driver-grid{grid-template-columns:repeat(3,1fr)}
      .scenario{grid-template-columns:repeat(3,1fr)}
    }
    @media(max-width:900px){
      .topbar,.hero,.strategy-strip{grid-template-columns:1fr;display:grid}
      .controls{justify-content:start}
      .shell{grid-template-columns:1fr}
      .sidebar{position:static}
      .sidenav{grid-auto-flow:column;grid-auto-columns:max-content;overflow-x:auto}
      .grid-2,.grid-3,.dev-grid{grid-template-columns:1fr}
      .principles{grid-template-columns:1fr}
      .driver-grid{grid-template-columns:repeat(2,1fr)}
      .form-grid{grid-template-columns:repeat(2,1fr)}
      .field.span4{grid-column:span 2}
      .brand{min-width:0}
    }
    @media(max-width:560px){
      .app{padding:9px}
      .metric-grid{grid-template-columns:repeat(2,1fr)}
      .driver-grid,.scenario{grid-template-columns:1fr}
      .commission-grid{grid-template-columns:1fr}
      .form-grid{grid-template-columns:1fr}
      .field.span2,.field.span4{grid-column:span 1}
      .funnel-row{grid-template-columns:100px 1fr 48px}
    }
    @media print{
      body{background:#fff}.topbar{position:static}.sidebar,.controls .print-hide,.scenario{display:none!important}
      .shell{grid-template-columns:1fr}
      .panel,.metric,.action-card{box-shadow:none;break-inside:avoid}
    }
  </style>
</head>
<body>
<div class="app">
  <header class="topbar">
    <div class="brand">
      <div class="mark">VA</div>
      <div>
        <h1>BDE Command Centre</h1>
        <p>Strategy → daily execution → verified revenue → commission → growth</p>
      </div>
    </div>
    <div class="controls">
      <!-- Locked context (display-only for phase 1) -->
      <div class="control">
        <label>Role view</label>
        <div class="readonly-field">BDE / Coordinator</div>
      </div>
      <div class="control">
        <label>Department</label>
        <div class="readonly-field">Virtual</div>
      </div>
      <div class="control">
        <label>Employee</label>
        <div class="readonly-field">Dorcas Mukami Murithi</div>
      </div>
      <div class="control">
        <label for="monthSelect">Period</label>
        <select id="monthSelect">
          <option>September 2026</option>
          <option>October 2026</option>
          <option>November 2026</option>
        </select>
      </div>
      <button class="icon-btn print-hide" id="themeBtn" type="button">Dark mode</button>
      <button class="icon-btn print-hide" id="printBtn" type="button">Print</button>
    </div>
  </header>

  <section class="strategy-strip">
    <div>
      <div class="eyebrow" id="strategyEyebrow">Your performance mandate</div>
      <h2 id="strategyTitle"></h2>
      <p id="strategyText"></p>
    </div>
    <div class="strategy-focus">
      <b>Today's strategic focus</b>
      <span id="todayFocus"></span>
    </div>
  </section>

  <div class="shell">
    <aside class="sidebar">
      <div class="nav-label">Sections</div>
      <nav class="sidenav" aria-label="Dashboard sections">
        <button class="tab active" data-view="overview">Command Centre</button>
        <button class="tab" data-view="pipeline">Pipeline &amp; Conversion</button>
        <button class="tab" data-view="commission">Commission Journey</button>
        <button class="tab" data-view="report">Embedded Reporting</button>
        <button class="tab" data-view="strategy">Strategy &amp; Scorecard</button>
        <button class="tab" data-view="developer">Developer Map</button>
      </nav>
    </aside>

    <main id="workspace" class="workspace"></main>
  </div>

  <div class="footer-note">Interactive prototype with illustrative data. Production figures must come from versioned targets, CRM transactions and Finance-cleared payments.</div>
</div>

<script>
(() => {
  "use strict";

  const departments = {
    "Virtual": {
      leader:"Francisca Ing'aa", leaderTitle:"BDO – Virtual Department",
      target:11504875, actual:7920000, forecast:11850000, pipeline:21100000, collection:0.87,
      strategyTitle:"Convert every enquiry into a managed next step and every free session into a payment opportunity.",
      strategyText:"The Virtual Department wins through fast response, relationship building, strong free-session attendance, human calls for hot leads, accurate automation, payment guidance and disciplined CRM follow-up.",
      focus:"Call every hot lead and payment promise first; then protect free-session attendance and same-day CRM updates.",
      drivers:[
        ["New enquiries",1260,"Monthly flow"],
        ["Hot leads",184,"Human follow-up"],
        ["Free-session attendance",318,"Qualified attendees"],
        ["First payments",286,"Finance verified"],
        ["CRM completeness",93,"% complete"]
      ],
      funnel:[["Enquiries",1260],["Qualified",760],["Free-session registered",510],["Attended",318],["Payment commitment",302],["First payment",286]],
      sources:[["Meta ads",38],["WhatsApp",24],["Database",16],["Referrals",13],["Website / AI",9]],
      team:[
        {name:"Purity Gatwiri",title:"BDE – Leadership Programmes",target:2200000,actual:1620000,pipeline:4600000,collection:.88,crm:96,trend:12,commissionKind:"virtual",units:58,unitTarget:72,notes:"Strengthen SMC and SLDP attendee conversion."},
        {name:"Maryanne Nafula Owour",title:"Sales – Leadership",target:1300000,actual:940000,pipeline:2400000,collection:.84,crm:91,trend:6,commissionKind:"virtual",units:39,unitTarget:50,notes:"Prioritize Friday payment promises."},
        {name:"Lucky Anindo",title:"BDE – Project-Based Courses",target:2200000,actual:1760000,pipeline:3900000,collection:.90,crm:95,trend:15,commissionKind:"virtual",units:64,unitTarget:75,notes:"On pace; add stretch target."},
        {name:"Dorcas Mukami Murithi",title:"Sales – Project Courses",target:1300000,actual:810000,pipeline:2050000,collection:.81,crm:89,trend:-4,commissionKind:"virtual",units:34,unitTarget:50,notes:"Needs a 7-day recovery plan."},
        {name:"Rachael Wambui Mwongela",title:"BDE – Data Analysis",target:2200000,actual:1850000,pipeline:4100000,collection:.91,crm:97,trend:11,commissionKind:"virtual",units:70,unitTarget:78,notes:"Protect collection and follow-up."},
        {name:"Joy Kendi",title:"Sales – Data Analysis",target:2104875,actual:700000,pipeline:2000000,collection:.79,crm:86,trend:-8,commissionKind:"virtual",units:27,unitTarget:70,notes:"Immediate call and campaign-quality intervention."}
      ],
      dailyRhythm:[
        ["8:00–8:30","Review new enquiries, overnight AI conversations, payment commitments and overdue follow-ups."],
        ["8:30–9:00","Receive daily first-payment, call, CRM and free-session targets."],
        ["9:00–10:30","Call hot leads and payment commitments before generic follow-up."],
        ["10:30–1:00","Qualify enquiries, invite and confirm free-session attendance."],
        ["2:00–4:45","Warm follow-up, objection handling, payment guidance and closing."],
        ["4:45–5:15","Complete CRM, report results and prepare next-day priority list."]
      ],
      principles:[
        ["Every enquiry has an owner","No enquiry should remain unattended or outside the CRM."],
        ["Human calls for high intent","Hot, institutional and payment-ready leads cannot remain in automation only."],
        ["Payment is the result","Interest and attendance are pipeline indicators; cleared first payments are achieved performance."]
      ]
    },
    "Corporate": {
      leader:"Edwin Otieno", leaderTitle:"BDO – Corporate Department",
      target:10000000, actual:6250000, forecast:10400000, pipeline:31800000, collection:0.90,
      strategyTitle:"Create institutional demand, reach decision-makers and move every account toward a commercial commitment.",
      strategyText:"Corporate growth comes from a Top-200 account system, Top-50 priorities, discovery meetings, tailored proposals, RFP discipline, open-programme innovation, cross-SBU conversion, collections and excellent delivery.",
      focus:"Advance the highest-value accounts into discovery, proposal, negotiation or deposit; no important account may sit without a dated next action.",
      drivers:[
        ["Top-200 captured",172,"Organizations"],
        ["Top-50 active",41,"Priority accounts"],
        ["Discovery meetings",19,"This month"],
        ["Proposals live",23,"KES 18.4M"],
        ["Open programmes",3,"Validated launches"]
      ],
      funnel:[["Target accounts",200],["Decision-makers reached",126],["Discovery held",58],["Qualified opportunity",39],["Proposal sent",23],["Negotiation / approval",12],["Won / deposit",7]],
      sources:[["Outbound accounts",31],["Cross-SBU referrals",22],["RFPs",18],["Alumni / referrals",17],["Open programmes",12]],
      team:[
        {name:"Josiah Kamau Mwangi",title:"BDE – Lead Generation",target:2000000,actual:1750000,pipeline:6800000,collection:.94,crm:98,trend:18,commissionKind:"corporate",units:17,unitTarget:20,notes:"High pipeline quality; accelerate two negotiations."},
        {name:"Hannah Wanjiku",title:"BDE – Proposal Development",target:2000000,actual:1410000,pipeline:5300000,collection:.89,crm:95,trend:9,commissionKind:"corporate",units:13,unitTarget:20,notes:"Prioritize proposal follow-up and decision dates."},
        {name:"Regina Juma",title:"BDE – Reports & Client Success",target:2000000,actual:930000,pipeline:3100000,collection:.85,crm:91,trend:-3,commissionKind:"corporate",units:8,unitTarget:20,notes:"Activate renewals, referrals and completed-assignment upsells."}
      ],
      dailyRhythm:[
        ["8:00–8:30","Review target accounts, overdue follow-ups, proposals, RFPs, collections and open-programme leads."],
        ["8:30–9:00","Receive exact revenue, meeting, proposal and follow-up outcomes for the day."],
        ["9:00–10:30","Call decision-makers and high-value opportunities."],
        ["10:30–1:00","Research accounts, map buying groups and prepare tailored commercial assets."],
        ["2:00–4:30","Discovery, proposals, negotiation, partner/alumni and cross-SBU engagement."],
        ["4:30–5:15","Update CRM, confirm every next action and submit the daily report."]
      ],
      principles:[
        ["Begin with the business problem","Do not begin with a brochure or fee; begin with priorities, risks and desired results."],
        ["Every meeting ends with a next step","Record the person, output and date; 'we shall get back to you' is not progress."],
        ["Every proposal is actively managed","Receipt, review status, objections, decision timing and payment route must be known."]
      ]
    },
    "International": {
      leader:"Erick Kwemoi Ndiema", leaderTitle:"BDO – International Programmes",
      target:24000000, actual:13650000, forecast:25100000, pipeline:45200000, collection:0.91,
      strategyTitle:"Build organization-sponsored country pipelines first, then use automation, calls, free training, alumni and local marketers to close the remaining gap.",
      strategyText:"Each country is a mini business unit. M&E and Data Analysis require independent pipelines, organization targets, local marketers, free-session plans, payment routes, forecasts and recovery actions.",
      focus:"Move organization sponsorships and payment commitments in every country; do not allow strong countries to hide weak ones.",
      drivers:[
        ["Countries scheduled",6,"September"],
        ["Paid participants",273,"of 480"],
        ["Org-sponsored",119,"Participants"],
        ["Countries at 80%+",3,"Need 4 to unlock"],
        ["Collection rate",91,"% cleared"]
      ],
      funnel:[["Historical / new leads",2800],["Qualified",1210],["Free-session attendees",720],["Committed",465],["Fully paid",273]],
      sources:[["Organizations",44],["Individual digital",21],["Alumni / VAMEPA",15],["Local marketers",13],["Free-session referrals",7]],
      countries:[
        {name:"Botswana",me:35,data:34,org:21,revenue:3450000},
        {name:"Namibia",me:33,data:32,org:18,revenue:3250000},
        {name:"Sierra Leone",me:30,data:28,org:16,revenue:2900000},
        {name:"The Gambia",me:25,data:22,org:14,revenue:2350000},
        {name:"Lesotho",me:18,data:20,org:12,revenue:1900000},
        {name:"Eswatini",me:15,data:11,org:8,revenue:1300000}
      ],
      team:[
        {name:"Kevin Mutura",title:"Coordinator – M&E",target:12000000,actual:7800000,pipeline:23100000,collection:.92,crm:96,trend:10,commissionKind:"international",units:156,unitTarget:240,notes:"Three countries qualify; one additional country needed for unlock.",countryCounts:[35,33,30,25,18,15]},
        {name:"John Maina Mwangi",title:"Coordinator – Data Analysis",target:12000000,actual:5850000,pipeline:22100000,collection:.90,crm:94,trend:7,commissionKind:"international",units:117,unitTarget:240,notes:"Two countries qualify; intensify Gambia and Sierra Leone.",countryCounts:[34,32,28,22,20,11]}
      ],
      dailyRhythm:[
        ["8:00–8:30","Review country/course dashboard, organization opportunities, representatives and payment promises."],
        ["8:30–9:00","Set exact organization, call, participant, collection and country-recovery targets."],
        ["9:00–11:00","Call institutions, nominees, alumni, hot leads and promised payments."],
        ["11:00–1:00","Run organization proposals, free-session invitations and local-marketer coordination."],
        ["2:00–4:30","Country follow-up, payment support, micro-messaging and weak-country recovery."],
        ["4:30–5:15","Update country CRM, Finance status, forecast and next-day priorities."]
      ],
      principles:[
        ["Organizations make scale possible","Several sponsored staff from one institution can move a country rapidly toward quorum."],
        ["Every country is visible","Country, course, source, owner, next action, paid, committed and weighted pipeline must be current."],
        ["Automation expands reach; humans close","Calls are compulsory for institutional, hot and payment-ready prospects."]
      ]
    },
    "Digital Solutions": {
      leader:"Alein Kawinzi Kagunza", leaderTitle:"BDO – Digital Solutions",
      target:4850000, actual:2790000, forecast:4990000, pipeline:14600000, collection:0.93,
      strategyTitle:"Turn Eval360 and 360 Appraisal into visible, trusted and fast-growing recurring-revenue solutions.",
      strategyText:"Growth requires product mastery, direct organization engagement, aggressive demonstrations, RFP intelligence, digital demand generation, reliable self-onboarding, strong adoption and proactive maintenance and renewals.",
      focus:"Move qualified organizations into demos and paid onboarding while protecting product readiness and recurring revenue.",
      drivers:[
        ["Qualified organizations",74,"Active pipeline"],
        ["Demos completed",21,"This month"],
        ["RFPs assessed",18,"100% within 24h"],
        ["Active paid users",412,"Across products"],
        ["Renewals visible",100,"% within 60 days"]
      ],
      funnel:[["Organizations identified",240],["Decision-makers reached",138],["Discovery",74],["Demo",45],["Proposal / pilot",26],["Paid onboarding",9]],
      sources:[["Direct organization outreach",32],["Digital campaigns",26],["RFP / procurement",18],["Professional platforms",14],["Cross-SBU referrals",10]],
      team:[
        {name:"Austin Abere",title:"BDE – Eval360",target:2150000,actual:1370000,pipeline:8900000,collection:.95,crm:98,trend:16,commissionKind:"eval360",units:84,unitTarget:100,corporateClients:1,maintenance:100000,notes:"Corporate setup pipeline strong; individual users need 16 more."},
        {name:"Ruth Ngari",title:"BDE – 360 Appraisal",target:1200000,actual:1020000,pipeline:5700000,collection:.92,crm:96,trend:22,commissionKind:"appraisal360",units:510,unitTarget:600,renewals:220000,notes:"30 paid staff to reach the 80% commission threshold."}
      ],
      dailyRhythm:[
        ["8:00–8:30","Review enquiries, RFP alerts, demos, onboarding issues, product defects and renewals."],
        ["8:30–9:00","Set decision-maker, demo, proposal, content, onboarding and revenue targets."],
        ["9:00–11:00","Call priority organizations, champions, procurement and payment contacts."],
        ["11:00–1:00","Discovery, RFP qualification, demo preparation and tailored value assets."],
        ["2:00–4:00","Run demos, executive briefings, proposals, pilots and onboarding support."],
        ["4:00–5:15","Follow up decisions, fix friction, update CRM and prepare next-day accounts."]
      ],
      principles:[
        ["Sell outcomes, not screens","Connect the product to institutional performance, reporting, decisions, development and accountability."],
        ["Every serious account reaches a tailored demo","A demo without discovery, decision-makers and a next step is only activity."],
        ["Onboarding friction is a revenue risk","Self-service and automated journeys must be tested repeatedly and corrected quickly."]
      ]
    },
    "Academic": {
      leader:"Hellen Letting", leaderTitle:"BDO – Academic Department",
      target:2400000, actual:1460000, forecast:2520000, pipeline:4600000, collection:0.96,
      strategyTitle:"Build the conversion machine first, then increase traffic and scale toward one million African learners.",
      strategyText:"The department owns system readiness, a self-service customer journey, digital lead quality, paid conversion, learner activation, institutional distribution, customer feedback and preparation for learner-created-course SaaS.",
      focus:"Fix any customer-journey friction immediately, protect paid conversion and activation, and expand university, college and employer channels.",
      drivers:[
        ["Fully paid learners",122,"of 200"],
        ["Activation rate",91,"% within 24h"],
        ["Checkout conversion",18.4,"%"],
        ["Critical defects",0,"Launch blockers"],
        ["Institutional pipeline",840,"Potential learners"]
      ],
      funnel:[["Landing-page visitors",7200],["Registrations",1320],["Checkout starts",610],["Fully paid",122],["Activated",111],["Weekly active",88]],
      sources:[["Paid digital",42],["Colleges / universities",23],["Database",14],["Employers",12],["Referrals",9]],
      team:[
        {name:"Florence Jemutai",title:"BDE – Professional Qualifications",target:1200000,actual:820000,pipeline:2500000,collection:.97,crm:98,trend:13,commissionKind:"academic",units:68,unitTarget:100,notes:"Improve checkout conversion and college presentations."},
        {name:"Rita Nazi",title:"BDE – Other Courses & Platform Growth",target:1200000,actual:640000,pipeline:2100000,collection:.95,crm:96,trend:8,commissionKind:"academic",units:54,unitTarget:100,notes:"Increase platform-level demand and institutional demos."}
      ],
      dailyRhythm:[
        ["8:00–8:30","Review registrations, payments, activation, automation, support tickets and system issues."],
        ["8:30–9:00","Set learner, revenue, campaign, institution and product-improvement outcomes."],
        ["9:00–10:00","Run the full customer-journey test and log evidence."],
        ["10:00–11:30","Review funnel leakage, abandoned journeys and high-value exceptions."],
        ["11:30–1:00","Institutional outreach, presentations, partnerships and demonstrations."],
        ["2:00–5:15","Content, experiments, issue closure, learner feedback, CRM and daily report."]
      ],
      principles:[
        ["Readiness before scale","Do not send paid traffic into a broken payment, onboarding, content or AI journey."],
        ["Conversion is the marketing test","Reach and enquiries matter, but fully paid and activated learners are the commercial result."],
        ["Every friction point has an owner","A problem must enter the issue log, be assigned, corrected, retested and closed."]
      ]
    }
  };

  const leaders = {
    BDM:{name:"Michael Obworo Mongere",title:"Business Development Manager",personalTarget:5000000,personalActual:3720000,personalPipeline:17400000},
    CEO:{name:"Dr. Benson Kiarie",title:"Founder & CEO"}
  };

  // Locked to a single BDE context for phase 1 (display-only selectors above).
  const state = {
    role:"BDE", department:"Virtual", user:"Dorcas Mukami Murithi", view:"overview",
    workingDays:22, elapsedDays:13, overrides:{}, theme:"light"
  };

  const fmt = new Intl.NumberFormat("en-KE",{maximumFractionDigits:0});
  const money = v => "KES " + fmt.format(Math.round(v || 0));
  const shortMoney = v => {
    const a=Math.abs(v||0);
    if(a>=1e9)return "KES "+(v/1e9).toFixed(1)+"B";
    if(a>=1e6)return "KES "+(v/1e6).toFixed(2)+"M";
    if(a>=1e3)return "KES "+(v/1e3).toFixed(0)+"K";
    return money(v);
  };
  const pct = v => ((v||0)*100).toFixed(v>=1?0:1)+"%";
  const esc = s => String(s??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
  const initials = name => name.split(/\s+/).map(x=>x[0]).slice(0,2).join("").toUpperCase();
  const deepClone = obj => JSON.parse(JSON.stringify(obj));

  function companyData(){
    const list=Object.entries(departments).map(([name,d])=>({name,...d}));
    return {
      target:list.reduce((a,d)=>a+d.target,0),
      actual:list.reduce((a,d)=>a+d.actual,0),
      forecast:list.reduce((a,d)=>a+d.forecast,0),
      pipeline:list.reduce((a,d)=>a+d.pipeline,0),
      collection:list.reduce((a,d)=>a+d.collection*d.actual,0)/Math.max(1,list.reduce((a,d)=>a+d.actual,0)),
      departments:list
    };
  }

  function scopeKey(){return [state.role,state.department,state.user].join("|")}
  function getOverride(){return state.overrides[scopeKey()]||{}}
  function currentStaff(){
    const d=departments[state.department];
    return d.team.find(x=>x.name===state.user)||d.team[0];
  }
  function baseScope(){
    if(state.role==="BDE"){
      const s=deepClone(currentStaff());
      s.name=s.name;s.title=s.title;s.department=state.department;s.level="Personal portfolio";
      s.strategyTitle=departments[state.department].strategyTitle;s.strategyText=departments[state.department].strategyText;s.focus=departments[state.department].focus;
      return s;
    }
    if(state.role==="BDO"){
      const d=deepClone(departments[state.department]);
      d.name=d.leader;d.title=d.leaderTitle;d.department=state.department;d.level="Department command centre";
      d.strategyTitle=departments[state.department].strategyTitle;d.strategyText=departments[state.department].strategyText;d.focus=departments[state.department].focus;
      return d;
    }
    const c=companyData();
    if(state.role==="BDM"){
      return {...c,name:leaders.BDM.name,title:leaders.BDM.title,department:"All five SBUs",level:"Commercial leadership",strategyTitle:"Make growth systematic across all five SBUs while remaining a direct strategic revenue producer.",strategyText:"The BDM must control consolidated revenue, qualified pipeline, proposals, strategic accounts, marketing-to-sales conversion, collections, CRM discipline, HOD performance and early recovery action.",focus:"Move blocked high-value accounts, correct weak SBUs and ensure every HOD has an evidence-based forecast and recovery action.",personalTarget:leaders.BDM.personalTarget,personalActual:leaders.BDM.personalActual,personalPipeline:leaders.BDM.personalPipeline};
    }
    return {...c,name:leaders.CEO.name,title:leaders.CEO.title,department:"Vantage Africa",level:"Enterprise leadership",strategyTitle:"See the entire organization clearly and intervene where leadership, resources or decisions will change the result.",strategyText:"The CEO dashboard consolidates revenue, forecasts, staff performance, strategic accounts, commissions, collections, product readiness and the few decisions requiring executive attention.",focus:"Protect organization-wide revenue, resolve the biggest bottleneck and support the opportunities with the greatest strategic value."};
  }
  function getScope(){
    const s=baseScope(),o=getOverride();
    ["target","actual","pipeline","collection","forecast"].forEach(k=>{if(o[k]!==undefined)s[k]=o[k]});
    return s;
  }

  function paceStatus(actual,target){
    const expected=target*(state.elapsedDays/state.workingDays);
    const pace=expected?actual/expected:0;
    return {pace,expected,status:pace>=1?"green":pace>=.85?"amber":"red",label:pace>=1?"On pace":pace>=.85?"At risk":"Needs intervention"};
  }

  function commissionForBDE(s){
    const att=s.target?s.actual/s.target:0;
    if(s.commissionKind==="virtual"){
      const eligible=att>=.8&&s.collection>=.8;
      return {current:eligible?s.actual*.05:0,atTarget:s.target*.05,unlock:eligible?"Commission currently unlocked":"Reach 80% of target and 80% fee collection",gates:[
        ["Portfolio target at 80%+",att>=.8,pct(att)],
        ["Fee collection at 80%+",s.collection>=.8,pct(s.collection)],
        ["CRM and qualifying client evidence",s.crm>=90,s.crm+"%"]
      ],rule:"5% of eligible fees actually collected once the portfolio and collection gates are satisfied."};
    }
    if(s.commissionKind==="academic"){
      const rate=att>=1?.04:att>=.8?.03:0;
      return {current:s.actual*rate,atTarget:s.target*.04,unlock:rate?"Current band: "+(rate*100)+"%":"Reach 80 fully paid clients and KES 960,000",gates:[
        ["Fully paid client threshold",s.units>=80,s.units+" / 80 minimum"],
        ["Revenue threshold",s.actual>=960000,shortMoney(s.actual)],
        ["Finance-cleared fees",s.collection>=.95,pct(s.collection)]
      ],rule:"3% at 80–99 fully paid clients and KES 960,000+; 4% at 100+ clients and KES 1.2M+."};
    }
    if(s.commissionKind==="appraisal360"){
      const rate=(s.units>=600&&s.actual>=1200000)?.04:(s.units>=480&&s.actual>=960000)?.03:0;
      const renewal=rate?s.renewals*.01:0;
      return {current:s.actual*rate+renewal,atTarget:1200000*.04+(s.renewals||0)*.01,unlock:rate?"New-business band unlocked":"Need "+Math.max(0,480-s.units)+" more paid staff to reach 80%",gates:[
        ["480 paid staff minimum",s.units>=480,s.units+" paid staff"],
        ["KES 960,000 minimum",s.actual>=960000,shortMoney(s.actual)],
        ["Renewal gate tied to new acquisition",rate>0,shortMoney(s.renewals||0)+" renewal base"]
      ],rule:"3% at 480–599 paid staff; 4% at 600+ paid staff; 1% on eligible renewals when current new business reaches 80%."};
    }
    if(s.commissionKind==="eval360"){
      const individualRate=s.units>=150?.10:s.units>=125?.075:s.units>=100?.05:0;
      const individualUnlocked=individualRate>0&&s.actual>=350000&&s.corporateClients>=1;
      const corporateUnlocked=s.corporateClients>=2&&s.units>=80&&s.actual>=280000;
      const individual=individualUnlocked?Math.min(s.actual,600000)*individualRate:0;
      const setup=corporateUnlocked?s.corporateClients*900000*.03:0;
      const maintenance=corporateUnlocked?(s.maintenance||0)*.025:0;
      const bonus=(s.units>=100&&s.actual>=350000&&s.corporateClients>=2)?25000:0;
      return {current:individual+setup+maintenance+bonus,atTarget:350000*.05+2*900000*.03+100000*.025+25000,unlock:bonus?"Balanced performance bonus unlocked":corporateUnlocked||individualUnlocked?"One stream unlocked; complete the balance":"Meet the individual and corporate support thresholds",gates:[
        ["100 active paying users",s.units>=100,s.units+" users"],
        ["At least KES 350,000 individual collections",s.actual>=350000,shortMoney(s.actual)],
        ["2 fully paid corporate setups",s.corporateClients>=2,(s.corporateClients||0)+" clients"],
        ["Maintenance current",s.collection>=.9,pct(s.collection)]
      ],rule:"Individual tier commission, 3% corporate setup, 2.5% maintenance and KES 25,000 balanced bonus when both full targets are achieved."};
    }
    if(s.commissionKind==="international"){
      const counts=s.countryCounts||[];
      const qualifying=counts.filter(x=>x>=32).length;
      const unlocked=qualifying>=4&&s.collection>=.95;
      const current=unlocked?counts.reduce((a,n)=>a+(n>=41?n*1000:n>=32?n*500:0),0):0;
      const atTarget=6*40*500;
      return {current,atTarget,unlock:unlocked?"Country commission unlocked":`Need ${Math.max(0,4-qualifying)} more country/countries at 32+ and 95% collection`,gates:[
        ["Minimum 4 of 6 countries at 80%+",qualifying>=4,qualifying+" qualifying"],
        ["Country threshold is 32 participants",qualifying>=4,counts.join(", ")],
        ["Portfolio fee collection at 95%+",s.collection>=.95,pct(s.collection)]
      ],rule:"KES 500 per student for countries with 32–40; KES 1,000 per student at 41+, only after the qualifying-country and 95% collection gates."};
    }
    const rate=att>=1?.03:att>=.8?.02:0;
    return {current:s.actual*rate,atTarget:s.target*.03,unlock:rate?"Configured Corporate commission band reached":"Reach the approved 80% threshold",gates:[
      ["Revenue threshold",att>=.8,pct(att)],
      ["Cleared collections",s.collection>=.9,pct(s.collection)],
      ["Acquisition ownership and CRM evidence",s.crm>=95,s.crm+"%"]
    ],rule:"Illustrative Corporate rule in this prototype. Production must read the approved, versioned Corporate Commission Rule Master."};
  }

  function commissionForScope(s){
    if(state.role==="BDE")return commissionForBDE(s);
    if(state.role==="BDO"){
      const att=s.target?s.actual/s.target:0;
      let current=0,atTarget=0,rule="",gates=[];
      if(state.department==="International"){
        const qualifying=s.countries.filter(c=>(c.me>=32||c.data>=32)&&c.org>=5).length;
        const bonus=att>=1?50000:att>=.9?30000:att>=.8?15000:0;
        current=bonus;atTarget=50000;rule="Organization sponsorship commission plus departmental leadership bonus; 30% can remain held until course and departmental gates are satisfied.";
        gates=[["At least 4 countries with 5+ sponsored participants",qualifying>=4,qualifying+" countries"],["Department reaches 80%+",att>=.8,pct(att)],["Both courses meet country quorum",s.countries.filter(c=>c.me>=32).length>=4&&s.countries.filter(c=>c.data>=32).length>=4,"M&E / Data balance"]];
      }else if(state.department==="Digital Solutions"){
        current=att>=1?40000:att>=.8?20000:0;atTarget=40000;rule="Leadership bonus is unlocked only when the HOD achieves the personal balance rules, both BDEs reach 80% and the department reaches the required band.";
        gates=[["Department reaches 80%+",att>=.8,pct(att)],["Both product lines contribute",s.team.every(x=>x.actual/x.target>=.8),s.team.map(x=>Math.round(x.actual/x.target*100)+"%").join(" / ")],["Collections verified",s.collection>=.9,pct(s.collection)]];
      }else if(state.department==="Academic"){
        const rate=att>=1?.025:att>=.8?.015:0;current=s.actual*rate;atTarget=s.target*.025;rule="1.5% at 80–99.99% and 2.5% at 100%+, subject to fully paid learners and revenue gates.";
        gates=[["160 learners and KES 1.92M minimum",s.actual>=1920000,shortMoney(s.actual)],["Both BDE portfolios visible",true,"2 portfolios"],["Finance-cleared revenue",s.collection>=.95,pct(s.collection)]];
      }else{
        current=att>=1?60000:att>=.8?30000:0;atTarget=60000;rule="Illustrative leadership amount. Production must read the approved HOD commission and hold-back rule for the selected department.";
        gates=[["Department reaches 80%+",att>=.8,pct(att)],["Balanced BDE performance",s.team.filter(x=>x.actual/x.target>=.8).length>=Math.ceil(s.team.length*.67),s.team.filter(x=>x.actual/x.target>=.8).length+" of "+s.team.length],["Collections and CRM complete",s.collection>=.9,pct(s.collection)]];
      }
      return {current,atTarget,unlock:current?"Leadership band currently visible":"Leadership gate not yet unlocked",gates,rule};
    }
    if(state.role==="BDM"){
      const orgAtt=s.actual/s.target,personal=s.personalActual;
      const personalComm=personal>=7500000?150000:personal>=6000000?120000:personal>=5000000?90000:personal>=4000000?60000:0;
      const sbus80=s.departments.filter(d=>d.actual/d.target>=.8).length;
      const leadership=orgAtt>=1.1?125000:orgAtt>=1?100000:orgAtt>=.9?75000:orgAtt>=.8?50000:0;
      const gated=sbus80>=4&&s.collection>=.9&&s.departments.every(d=>d.actual/d.target>=.5);
      return {current:gated?personalComm+leadership:personalComm*.7,atTarget:90000+100000,unlock:gated?"Organization leadership gate unlocked":"Complete balanced-SBU and 90% collection gates",gates:[
        ["Organization reaches 80%+",orgAtt>=.8,pct(orgAtt)],
        ["At least 4 of 5 SBUs at 80%+",sbus80>=4,sbus80+" of 5"],
        ["No SBU below 50%",s.departments.every(d=>d.actual/d.target>=.5),s.departments.filter(d=>d.actual/d.target<.5).length+" below"],
        ["Organization collection at 90%+",s.collection>=.9,pct(s.collection)],
        ["Personal strategic sales",personal>=4000000,shortMoney(personal)]
      ],rule:"Personal strategic acquisition commission plus organization-wide leadership commission, with a 30% leadership hold-back and balanced-SBU gates."};
    }
    const exposure=Object.values(departments).flatMap(d=>d.team).reduce((sum,x)=>sum+commissionForBDE(x).current,0);
    return {current:exposure,atTarget:exposure*1.65,unlock:"CEO sees exposure, payable amount and unresolved gates; approval remains with Finance and authorized management.",gates:[
      ["Finance-cleared revenue source",true,"Required"],
      ["No double counting",true,"Transaction-level ownership"],
      ["Rule version stored",true,"Effective-dated"],
      ["Approval and audit trail",true,"Required"]
    ],rule:"The CEO view does not calculate a personal commission. It shows enterprise commission exposure, estimated payable amounts, hold-backs and unresolved exceptions."};
  }

  function roleMandate(){
    if(state.role==="BDE")return "Personal execution dashboard";
    if(state.role==="BDO")return "Department leadership dashboard";
    if(state.role==="BDM")return "Five-SBU commercial command dashboard";
    return "Enterprise intervention dashboard";
  }

  function updateStrategy(){
    const s=getScope();
    document.getElementById("strategyEyebrow").textContent=roleMandate();
    document.getElementById("strategyTitle").textContent=s.strategyTitle;
    document.getElementById("strategyText").textContent=s.strategyText;
    document.getElementById("todayFocus").textContent=s.focus;
  }

  function roleMetrics(s){
    const ps=paceStatus(s.actual,s.target);
    const comm=commissionForScope(s);
    if(state.role==="BDE"){
      const units=s.units||0,targetUnits=s.unitTarget||0;
      return [
        ["Monthly target",shortMoney(s.target),"Approved personal target","neutral"],
        ["Cleared revenue",shortMoney(s.actual),pct(s.actual/s.target)+" of target","up"],
        ["Volume achieved",fmt.format(units),targetUnits?`of ${fmt.format(targetUnits)} target`:"Qualifying units","neutral"],
        ["Qualified pipeline",shortMoney(s.pipeline),(s.pipeline/s.target).toFixed(1)+"× target coverage","up"],
        ["Commission estimate",shortMoney(comm.current),"Current eligible estimate","neutral"],
        ["Daily pace needed",shortMoney(Math.max(0,(s.target-s.actual)/(state.workingDays-state.elapsedDays))),`${state.workingDays-state.elapsedDays} working days left`,"neutral"]
      ];
    }
    if(state.role==="BDO"){
      return [
        ["Department target",shortMoney(s.target),"Approved SBU target","neutral"],
        ["Cleared revenue",shortMoney(s.actual),pct(s.actual/s.target)+" attainment","up"],
        ["Month-end forecast",shortMoney(s.forecast),pct(s.forecast/s.target)+" projected","neutral"],
        ["Qualified pipeline",shortMoney(s.pipeline),(s.pipeline/s.target).toFixed(1)+"× target coverage","up"],
        ["Team at 80%+",s.team.filter(x=>x.actual/x.target>=.8).length+" / "+s.team.length,"Balanced performance","neutral"],
        ["Leadership incentive",shortMoney(comm.current),comm.unlock,"neutral"]
      ];
    }
    if(state.role==="BDM"){
      const sbus=s.departments.filter(d=>d.actual/d.target>=.8).length;
      return [
        ["Organization target",shortMoney(s.target),"All five SBUs","neutral"],
        ["Cleared revenue",shortMoney(s.actual),pct(s.actual/s.target)+" attainment","up"],
        ["Month-end forecast",shortMoney(s.forecast),pct(s.forecast/s.target)+" projected","neutral"],
        ["BDM personal sales",shortMoney(s.personalActual),pct(s.personalActual/s.personalTarget)+" of KES 5M","neutral"],
        ["SBUs at 80%+",sbus+" / 5","Balanced-SBU gate","neutral"],
        ["Commission estimate",shortMoney(comm.current),"Personal + leadership","neutral"]
      ];
    }
    return [
      ["Organization target",shortMoney(s.target),"Approved five-SBU plan","neutral"],
      ["Cleared revenue",shortMoney(s.actual),pct(s.actual/s.target)+" attainment","up"],
      ["Month-end forecast",shortMoney(s.forecast),pct(s.forecast/s.target)+" projected","neutral"],
      ["Qualified pipeline",shortMoney(s.pipeline),(s.pipeline/s.target).toFixed(1)+"× target","up"],
      ["Collection rate",pct(s.collection),"Finance-cleared receipts","neutral"],
      ["Commission exposure",shortMoney(comm.current),"Current estimated exposure","neutral"]
    ];
  }

  function metricHTML(items){
    return `<div class="metric-grid">${items.map(([label,value,note,delta])=>`
      <div class="metric">
        <div class="label">${esc(label)}</div>
        <div class="value">${esc(value)}</div>
        <div class="note">${esc(note)}</div>
        <div class="delta ${delta||"neutral"}">${delta==="up"?"↑ Positive movement":delta==="down"?"↓ Below pace":"• Live from CRM / Finance"}</div>
      </div>`).join("")}</div>`;
  }

  function scenarioHTML(s){
    return `<section class="panel">
      <div class="panel-head"><div><h3>Interactive scenario controls</h3><p>Change the illustrative figures to see the dashboard, pacing and commission journey respond.</p></div><span class="badge blue">Demo controls</span></div>
      <div class="scenario">
        <div class="control"><label>Target revenue</label><input class="input scenario-input" data-key="target" type="number" value="${Math.round(s.target)}"></div>
        <div class="control"><label>Cleared revenue</label><input class="input scenario-input" data-key="actual" type="number" value="${Math.round(s.actual)}"></div>
        <div class="control"><label>Qualified pipeline</label><input class="input scenario-input" data-key="pipeline" type="number" value="${Math.round(s.pipeline)}"></div>
        <div class="control"><label>Collection rate %</label><input class="input scenario-input" data-key="collection" type="number" min="0" max="100" step="1" value="${Math.round(s.collection*100)}"></div>
        <div class="control"><label>Forecast revenue</label><input class="input scenario-input" data-key="forecast" type="number" value="${Math.round(s.forecast||s.actual)}"></div>
        <button class="ghost-btn" id="resetScenario" type="button">Reset</button>
      </div>
    </section>`;
  }

  function trendSVG(s){
    const target=s.target||1,actual=s.actual||0,forecast=s.forecast||actual;
    const monthly=[.12,.24,.36,.46,.58,.68,.78,.88,1].map((x,i)=>i<6?actual*(x/.68):actual+(forecast-actual)*((i-5)/3));
    const max=Math.max(target,forecast,...monthly)*1.08;
    const w=620,h=190,p=28;
    const pts=monthly.map((v,i)=>[p+i*(w-2*p)/(monthly.length-1),h-p-v/max*(h-2*p)]);
    const line=pts.map((q,i)=>(i?"L":"M")+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ");
    const area=`M${pts[0][0]},${h-p} ${pts.map(q=>"L"+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ")} L${pts.at(-1)[0]},${h-p} Z`;
    const targetY=h-p-target/max*(h-2*p);
    return `<svg class="chart" viewBox="0 0 ${w} ${h}" role="img" aria-label="Revenue pace and forecast">
      ${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${p}" y1="${p+t*(h-2*p)}" x2="${w-p}" y2="${p+t*(h-2*p)}"/>`).join("")}
      <line x1="${p}" y1="${targetY}" x2="${w-p}" y2="${targetY}" stroke="var(--green)" stroke-dasharray="5 5"/>
      <text x="${w-p}" y="${targetY-5}" text-anchor="end">Target ${shortMoney(target)}</text>
      <path class="area" d="${area}"/><path class="line" d="${line}"/>
      ${pts.map(q=>`<circle class="dot" cx="${q[0]}" cy="${q[1]}" r="4"/>`).join("")}
      <text x="${p}" y="${h-5}">Start</text><text x="${w/2}" y="${h-5}" text-anchor="middle">Today</text><text x="${w-p}" y="${h-5}" text-anchor="end">Month end</text>
    </svg>`;
  }

  function actionItems(s){
    if(state.role==="BDE"){
      const generic=[
        ["red","Call payment-ready prospects","Complete all overdue payment promises and record the outcome.","Before 10:30"],
        ["amber","Move priority opportunities","Every hot or institutional lead must have a dated commercial next step.","Today"],
        ["blue","Protect CRM evidence","Update calls, meetings, objections, proposal status and payment evidence.","Before report"],
        ["green","Create tomorrow's advantage","Prepare the top five prospects, decision-makers or learner exceptions for the next day.","4:45 PM"]
      ];
      if(state.department==="Academic")generic.splice(1,0,["red","Run customer-journey test","Verify landing page, checkout, payment, activation, content access and AI support.","9:00 AM"]);
      if(state.department==="Digital Solutions")generic.splice(1,0,["red","Progress demos and onboarding","Confirm discovery, decision-makers, demo objective, proposal and onboarding issue status.","Today"]);
      if(state.department==="International")generic.splice(1,0,["red","Close country and organization gaps","Prioritize countries below 32 and organizations able to sponsor several participants.","Today"]);
      return generic;
    }
    if(state.role==="BDO"){
      return [
        ["red","Review all red portfolios","Assign a named recovery action, owner and review time for every red BDE, product, course or country.","8:30 AM"],
        ["amber","Control the top opportunities","Review the top five opportunities per BDE and personally support high-value or stalled accounts.","Before noon"],
        ["blue","Check lead flow and CRM quality","Compare actual lead volume, quality, next actions and collection commitments with required pace.","3:30 PM"],
        ["green","Coach and recognize","Document one coaching action for weak performance and one recognition action for strong performance.","Today"]
      ];
    }
    if(state.role==="BDM"){
      return [
        ["red","Recover the weakest SBU","Require a quantified seven-day recovery forecast and named opportunity list.","Today"],
        ["amber","Unblock strategic accounts","Use executive access, pricing, partnerships or internal coordination to move high-value deals.","Today"],
        ["blue","Audit HOD forecasts","Every SBU forecast must be supported by stage, value, probability, owner and next action.","Before weekly review"],
        ["green","Protect balanced performance","Strong results in one SBU must not hide serious underperformance elsewhere.","Ongoing"]
      ];
    }
    return [
      ["red","Resolve the largest organization gap","Direct the responsible leader to present the exact gap, causes, recovery plan and decision required.","Today"],
      ["amber","Intervene in strategic opportunities","Prioritize accounts where CEO access can materially improve trust, speed or deal value.","As scheduled"],
      ["blue","Protect cash and recurring revenue","Review collections, renewals, maintenance, major overdue balances and commission exposure.","Daily"],
      ["green","Recognize and multiply what works","Use top performers and winning channels to strengthen weaker teams and markets.","Weekly"]
    ];
  }

  function actionHTML(s){
    return `<div class="list">${actionItems(s).map(([c,b,p,d])=>`<div class="action-card"><span class="priority-dot ${c}"></span><div><b>${esc(b)}</b><p>${esc(p)}</p></div><span class="due">${esc(d)}</span></div>`).join("")}</div>`;
  }

  function driverHTML(d){
    return `<div class="driver-grid">${d.drivers.map(([l,n,s])=>`<div class="driver"><div class="top"><b>${esc(l)}</b><span class="badge blue">Live</span></div><div class="num">${typeof n==="number"?fmt.format(n):esc(n)}</div><small>${esc(s)}</small></div>`).join("")}</div>`;
  }

  function teamTable(d){
    return `<div class="table-wrap"><table><thead><tr><th>Employee / portfolio</th><th>Target</th><th>Actual</th><th>Achievement</th><th>Pipeline</th><th>CRM</th><th>Trend</th><th>Status / leadership action</th></tr></thead><tbody>
      ${d.team.map(x=>{
        const a=x.actual/x.target,ps=paceStatus(x.actual,x.target),c=commissionForBDE(x);
        return `<tr>
          <td><div class="person"><span class="avatar">${initials(x.name)}</span><div><b>${esc(x.name)}</b><span>${esc(x.title)}</span></div></div></td>
          <td>${shortMoney(x.target)}</td><td>${shortMoney(x.actual)}</td>
          <td><span class="score">${pct(a)}</span><div class="progress-wrap"><div class="track"><div class="fill" style="width:${Math.min(100,a*100)}%"></div></div></div></td>
          <td>${shortMoney(x.pipeline)}</td><td>${x.crm}%</td><td class="${x.trend>=0?"up":"down"}">${x.trend>=0?"↑":"↓"} ${Math.abs(x.trend)}%</td>
          <td><span class="badge ${ps.status}">${ps.label}</span><div style="font-size:10px;color:var(--muted);margin-top:5px">${esc(x.notes)} · Comm. ${shortMoney(c.current)}</div></td>
        </tr>`}).join("")}
      </tbody></table></div>`;
  }

  function sbuTable(c){
    return `<div class="table-wrap"><table><thead><tr><th>SBU</th><th>Target</th><th>Cleared revenue</th><th>Attainment</th><th>Forecast</th><th>Pipeline</th><th>Collection</th><th>Leadership response</th></tr></thead><tbody>
      ${c.departments.map(d=>{
        const a=d.actual/d.target,ps=paceStatus(d.actual,d.target);
        return `<tr><td><b>${esc(d.name)}</b><div style="font-size:10px;color:var(--muted)">${esc(d.leader)}</div></td>
        <td>${shortMoney(d.target)}</td><td>${shortMoney(d.actual)}</td><td><span class="score">${pct(a)}</span></td>
        <td>${shortMoney(d.forecast)}</td><td>${shortMoney(d.pipeline)}</td><td>${pct(d.collection)}</td>
        <td><span class="badge ${ps.status}">${ps.label}</span><div style="font-size:10px;color:var(--muted);margin-top:4px">${ps.status==="red"?"Recovery plan and daily monitoring":ps.status==="amber"?"Corrective action within 24 hours":"Protect quality and pursue stretch"}</div></td></tr>`;
      }).join("")}
      </tbody></table></div>`;
  }

  function overview(){
    const s=getScope(),ps=paceStatus(s.actual,s.target),comm=commissionForScope(s),att=s.actual/s.target;
    const orgContext=state.role==="BDE"?departments[state.department]:s;
    return `
      <section class="hero">
        <div class="panel">
          <div class="panel-head">
            <div><h3>${esc(s.name)} — ${esc(s.title)}</h3><p>${esc(s.level)} · ${esc(s.department)} · ${esc(document.getElementById("monthSelect").value)}</p></div>
            <span class="badge ${ps.status}">${ps.label} · pace ${pct(ps.pace)}</span>
          </div>
          ${metricHTML(roleMetrics(s))}
        </div>
        <div class="panel">
          <div class="panel-head"><div><h3>Progress to target</h3><p>Attainment, expected pace and remaining working days.</p></div><span class="badge ${att>=1?"green":att>=.8?"amber":"red"}">${pct(att)}</span></div>
          <div class="progress-label"><span>Cleared revenue</span><b>${shortMoney(s.actual)} / ${shortMoney(s.target)}</b></div>
          <div class="track"><div class="fill" style="width:${Math.min(100,att*100)}%"></div></div>
          <div class="commission-grid">
            <div class="mini"><span>Expected by today</span><b>${shortMoney(ps.expected)}</b></div>
            <div class="mini"><span>Remaining gap</span><b>${shortMoney(Math.max(0,s.target-s.actual))}</b></div>
            <div class="mini"><span>Days left</span><b>${state.workingDays-state.elapsedDays}</b></div>
          </div>
          <div class="callout" style="margin-top:11px"><strong>Keep going:</strong> ${ps.status==="green"?"You are at or above required pace. Protect collections, quality and stretch opportunities.":ps.status==="amber"?"You are close to pace. Focus on the opportunities nearest to payment and remove the biggest conversion blocker today.":"The current pace will miss target. Activate a quantified recovery plan now, not at month end."}</div>
        </div>
      </section>

      ${scenarioHTML(s)}

      <section class="grid-2">
        <div class="panel">
          <div class="panel-head"><div><h3>Revenue pace and month-end forecast</h3><p>The forecast should change whenever stage, probability, payment date or cleared revenue changes.</p></div><span class="badge blue">${shortMoney(s.forecast||s.actual)} forecast</span></div>
          ${trendSVG(s)}
        </div>
        <div class="panel">
          <div class="panel-head"><div><h3>Commission journey</h3><p>Transparent estimate, unlock conditions and the next earning band.</p></div><span class="badge ${comm.current>0?"green":"amber"}">${comm.current>0?"Eligible estimate":"Not yet unlocked"}</span></div>
          <div class="commission-road">
            <div class="road"><div class="fill" style="width:${Math.min(100,att/1.2*100)}%"></div></div>
            <div class="marker m80"><span>80%</span></div><div class="marker m100"><span>100%</span></div><div class="marker m120"><span>120%</span></div>
          </div>
          <div class="commission-grid">
            <div class="mini"><span>Current estimate</span><b>${shortMoney(comm.current)}</b></div>
            <div class="mini"><span>At target</span><b>${shortMoney(comm.atTarget)}</b></div>
            <div class="mini"><span>Extra available</span><b>${shortMoney(Math.max(0,comm.atTarget-comm.current))}</b></div>
          </div>
          <div class="callout" style="margin-top:10px"><strong>Next action:</strong> ${esc(comm.unlock)}</div>
        </div>
      </section>

      <section class="grid-2">
        <div class="panel"><div class="panel-head"><div><h3>Today's action centre</h3><p>Prioritized actions based on pace, pipeline, CRM and commission gates.</p></div><span class="badge red">Action required</span></div>${actionHTML(s)}</div>
        <div class="panel"><div class="panel-head"><div><h3>Execution drivers</h3><p>${state.role==="BDE"?"Department context that shapes your personal execution.":"The operational drivers that must remain visible."}</p></div><span class="badge blue">${esc(state.role==="BDE"?state.department:s.department)}</span></div>${driverHTML(orgContext)}</div>
      </section>

      <section class="panel">
        <div class="panel-head"><div><h3>${state.role==="BDE"?"Your department performance context":state.role==="BDO"?"BDE performance and coaching view":"Five-SBU performance comparison"}</h3><p>Every figure should drill into the underlying leads, opportunities, payments, actions and evidence.</p></div><span class="badge blue">Drill-down enabled</span></div>
        ${state.role==="BDE"?teamTable(departments[state.department]):state.role==="BDO"?teamTable(s):sbuTable(s)}
      </section>`;
  }

  function funnelHTML(s){
    const d=state.role==="BDE"||state.role==="BDO"?departments[state.department]:null;
    const funnel=d?d.funnel:[["Enterprise leads",7800],["Qualified",3640],["Meetings / sessions / demos",1840],["Proposal / commitment",920],["Payment / activation",608]];
    const max=funnel[0][1];
    return `<div class="funnel">${funnel.map(([label,n],i)=>`<div class="funnel-row"><label>${esc(label)}</label><div class="funnel-bar"><div style="width:${Math.max(8,n/max*100)}%">${fmt.format(n)}</div></div><span class="conversion">${i?Math.round(n/funnel[i-1][1]*100)+"%":"100%"}</span></div>`).join("")}</div>`;
  }

  function priorityTable(s){
    let rows=[];
    if(state.role==="BDE"){
      const name=s.name;
      if(state.department==="Corporate") rows=[
        ["Kenya Revenue Authority","Discovery held","KES 2.8M","Decision meeting","Tomorrow",name],
        ["AAR Insurance","Proposal sent","KES 1.6M","Confirm review team","Today",name],
        ["Manufacturers Association","Qualified","KES 900K","Book executive briefing","Friday",name],
        ["AI for Leaders cohort","Registrations","KES 620K","Call sponsor organizations","Today",name]
      ];
      else if(state.department==="International") rows=[
        ["Ministry of Finance – Botswana","Nomination pending","18 participants","Confirm approval date","Today",name],
        ["NGO Consortium – Namibia","Proposal sent","12 participants","Call country director","Tomorrow",name],
        ["Central Bank – Sierra Leone","Discovery","10 participants","Send team package","Today",name],
        ["Alumni employer network","Nurture","8 participants","Request introductions","Friday",name]
      ];
      else if(state.department==="Digital Solutions") rows=[
        ["Regional NGO Consortium","Demo scheduled","KES 900K","Prepare tailored Eval360 demo","Tomorrow",name],
        ["Manufacturing Group","Proposal","420 staff","Confirm procurement route","Today",name],
        ["Government Planning Unit","RFP qualified","KES 4.2M","Complete bid/no-bid review","Today",name],
        ["SME Founder Network","Campaign","180 staff potential","Schedule group briefing","Friday",name]
      ];
      else if(state.department==="Academic") rows=[
        ["University partnership","Presentation booked","240 learners","Confirm audience and tracked link","Thursday",name],
        ["College network","Proposal","160 learners","Send cohort package","Today",name],
        ["Employer CPD programme","Discovery","75 learners","Book platform demo","Friday",name],
        ["Abandoned checkout segment","Reactivation","43 prospects","Test recovery sequence","Today",name]
      ];
      else rows=[
        ["Hot payment commitments","Closing","KES 380K","Complete calls","Today",name],
        ["Free-session attendees","Follow-up","64 prospects","Payment and objection calls","Today",name],
        ["Institutional sponsorship lead","Discovery","24 participants","Book HR briefing","Tomorrow",name],
        ["Old enquiry segment","Reactivation","120 prospects","Run personalized sequence","Friday",name]
      ];
    }else{
      const source=state.role==="BDO"?state.department:"All SBUs";
      rows=[
        ["High-value organization A","Negotiation","KES 5.8M","Executive decision call","Today",source],
        ["Government / donor programme","Proposal / RFP","KES 8.4M","Compliance review","Tomorrow",source],
        ["Multi-participant sponsorship","Approval","KES 3.2M","Confirm nominee list","Today",source],
        ["Recurring digital account","Renewal","KES 1.1M","Resolve adoption issue","Friday",source],
        ["Open / academic programme channel","Campaign","KES 2.4M","Review ROI and conversion","Today",source]
      ];
    }
    return `<div class="table-wrap"><table><thead><tr><th>Account / opportunity</th><th>Stage</th><th>Value / volume</th><th>Next action</th><th>Due</th><th>Owner</th></tr></thead><tbody>${rows.map(r=>`<tr>${r.map((v,i)=>`<td>${i===0?"<b>"+esc(v)+"</b>":esc(v)}</td>`).join("")}</tr>`).join("")}</tbody></table></div>`;
  }

  function sourceHTML(){
    const d=state.role==="BDE"||state.role==="BDO"?departments[state.department]:null;
    const sources=d?d.sources:[["Direct / strategic accounts",29],["Digital campaigns",24],["Organizations / partnerships",21],["Referrals / alumni",15],["RFPs / procurement",11]];
    const max=Math.max(...sources.map(x=>x[1]));
    return sources.map(([n,v])=>`<div class="source-row"><span>${esc(n)}</span><div class="bar"><div style="width:${v/max*100}%"></div></div><b>${v}%</b></div>`).join("");
  }

  function pipeline(){
    const s=getScope();
    return `
      <section class="grid-2">
        <div class="panel"><div class="panel-head"><div><h3>Acquisition and conversion funnel</h3><p>Click-through production view should filter by owner, department, course/product, country, source and date.</p></div><span class="badge blue">Live funnel</span></div>${funnelHTML(s)}</div>
        <div class="panel"><div class="panel-head"><div><h3>Lead-source contribution</h3><p>The production version should show volume, quality, conversion, cost and cleared revenue by source.</p></div><span class="badge blue">Source ROI</span></div>${sourceHTML()}</div>
      </section>
      <section class="panel"><div class="panel-head"><div><h3>Priority opportunity control</h3><p>No important opportunity may exist only in email, WhatsApp, a notebook or memory.</p></div><span class="badge red">Next action required</span></div>${priorityTable(s)}</section>
      <section class="grid-3">
        <div class="panel"><div class="panel-head"><div><h3>Stale-lead alerts</h3><p>Live opportunities without a meaningful interaction inside the configured period.</p></div></div>
          ${["5 hot leads have no action today","3 proposals have no confirmed review date","11 payment promises are overdue"].map((x,i)=>`<div class="action-card"><span class="priority-dot ${i===0?"red":"amber"}"></span><div><b>${x}</b><p>Open the filtered list and assign the next action.</p></div><span class="due">Open</span></div>`).join("")}
        </div>
        <div class="panel"><div class="panel-head"><div><h3>Conversion-quality alerts</h3><p>Volume alone is not enough; the system must identify the stage causing leakage.</p></div></div>
          ${["Lead-to-qualified conversion below benchmark","Strong attendance but weak payment conversion","High proposal value with low decision-maker access"].map(x=>`<div class="action-card"><span class="priority-dot amber"></span><div><b>${x}</b><p>Compare message, audience, ownership and follow-up quality.</p></div><span class="due">Review</span></div>`).join("")}
        </div>
        <div class="panel"><div class="panel-head"><div><h3>Cross-SBU opportunity engine</h3><p>Internal relationships should create larger institutional and recurring opportunities.</p></div></div>
          ${["Virtual participant → employer briefing","International alumnus → ministry sponsorship","Training client → Eval360 / 360 opportunity","Academic employer → corporate cohort"].map(x=>`<div class="action-card"><span class="priority-dot blue"></span><div><b>${x}</b><p>Record source SBU, receiving owner, value and feedback.</p></div><span class="due">Route</span></div>`).join("")}
        </div>
      </section>`;
  }

  function commission(){
    const s=getScope(),c=commissionForScope(s),att=s.actual/s.target;
    return `
      <section class="hero">
        <div class="panel">
          <div class="panel-head"><div><h3>Your transparent commission journey</h3><p>The employee should always understand the current estimate, the applicable rule, unresolved gates and what is required to move to the next band.</p></div><span class="badge ${c.current?"green":"amber"}">${c.current?"Current estimate":"Locked"}</span></div>
          <div class="commission-road">
            <div class="road"><div class="fill" style="width:${Math.min(100,att/1.2*100)}%"></div></div>
            <div class="marker m80"><span>80%</span></div><div class="marker m100"><span>100%</span></div><div class="marker m120"><span>120%</span></div>
          </div>
          <div class="commission-grid">
            <div class="mini"><span>Estimated now</span><b>${shortMoney(c.current)}</b></div>
            <div class="mini"><span>Estimated at target</span><b>${shortMoney(c.atTarget)}</b></div>
            <div class="mini"><span>Additional earning</span><b>${shortMoney(Math.max(0,c.atTarget-c.current))}</b></div>
          </div>
          <div class="callout" style="margin-top:12px"><strong>Commission explanation:</strong> ${esc(c.rule)}</div>
        </div>
        <div class="panel">
          <div class="panel-head"><div><h3>Next earning milestone</h3><p>Plain-language encouragement linked to the exact remaining gap.</p></div></div>
          <div style="font-size:25px;font-weight:850;line-height:1.2;margin:15px 0">${shortMoney(Math.max(0,s.target-s.actual))}</div>
          <div style="color:var(--muted);font-size:12px">remaining to the full revenue target</div>
          <div class="callout" style="margin-top:15px"><strong>Recommended push:</strong> ${esc(c.unlock)}. Concentrate on verified opportunities nearest to payment rather than increasing unqualified activity.</div>
        </div>
      </section>
      <section class="grid-2">
        <div class="panel"><div class="panel-head"><div><h3>Eligibility checklist</h3><p>Every commission result must explain why it is unlocked, held or unavailable.</p></div></div>
          <div class="checklist">${c.gates.map(([n,ok,v])=>`<div class="check ${ok?"pass":"fail"}"><span class="symbol">${ok?"✓":"!"}</span><div><b>${esc(n)}</b><div style="font-size:10px;color:var(--muted)">${ok?"Condition satisfied":"Condition not yet satisfied"}</div></div><span>${esc(v)}</span></div>`).join("")}</div>
        </div>
        <div class="panel"><div class="panel-head"><div><h3>Commission audit trail</h3><p>The production CRM must keep a calculation trace for every amount.</p></div></div>
          ${[
            ["Rule version","COMM-2026-09-v1","Effective-dated and locked after month close"],
            ["Revenue source","Finance-cleared payments","Invoices and promises excluded"],
            ["Ownership","CRM acquisition owner","Joint splits require prior written approval"],
            ["Hold-back","Leadership / balance gate","Displayed separately from payable amount"],
            ["Reversals","Refunds and credit notes","Recalculate and preserve audit history"]
          ].map(r=>`<div class="action-card"><span class="priority-dot blue"></span><div><b>${r[0]}: ${r[1]}</b><p>${r[2]}</p></div><span class="due">Trace</span></div>`).join("")}
        </div>
      </section>
      <section class="panel"><div class="panel-head"><div><h3>Three-month consistency journey</h3><p>Recognition and performance support should be visible, evidence-based and never triggered automatically without authorized review.</p></div><span class="badge blue">Month 2 of 3</span></div>
        <div class="grid-3">
          <div class="mini"><span>Month 1</span><b>Target achieved</b><div class="up">↑ Verified</div></div>
          <div class="mini"><span>Month 2</span><b>${att>=1?"On track":"Recovery required"}</b><div class="${att>=1?"up":"neutral"}">${pct(att)} current attainment</div></div>
          <div class="mini"><span>Month 3</span><b>Future period</b><div class="neutral">Consistency reward pending</div></div>
        </div>
      </section>`;
  }

  function reportFields(role,s){
    if(role==="BDE") return [
      ["Daily target","number",Math.round(s.target/state.workingDays)],
      ["Actual cleared revenue today","number",Math.round(s.actual/state.elapsedDays)],
      ["New enquiries / accounts","number",38],
      ["Qualified leads","number",19],
      ["Calls / meaningful conversations","number",14],
      ["Meetings / demos / sessions","number",3],
      ["Proposals / payment links sent","number",7],
      ["Payments / activations today","number",5],
      ["Top opportunities and next actions","textarea","1. Priority organization – decision call tomorrow\n2. Payment promise – follow up 10:00 AM\n3. Demo prospect – send tailored agenda"],
      ["Marketing / automation / product observation","textarea","Best source, weakest source, AI issue, broken link, onboarding friction or message that converted."],
      ["What worked and what prevented conversion","textarea","Record evidence and learning, not general narration."],
      ["Support required and tomorrow's plan","textarea","Named support owner, deadline, top five prospects and tomorrow's target."]
    ];
    if(role==="BDO") return [
      ["Department daily revenue target","number",Math.round(s.target/state.workingDays)],
      ["Actual cleared revenue today","number",Math.round(s.actual/state.elapsedDays)],
      ["BDEs on / above pace","number",s.team.filter(x=>paceStatus(x.actual,x.target).status==="green").length],
      ["Red portfolios","number",s.team.filter(x=>paceStatus(x.actual,x.target).status==="red").length],
      ["Qualified pipeline value","number",s.pipeline],
      ["Meetings / demos / sessions today","number",6],
      ["Proposals / commitments moved","number",4],
      ["Collection commitments due","number",9],
      ["BDE performance and coaching actions","textarea",s.team.map(x=>`${x.name}: ${x.notes}`).join("\n")],
      ["Product / course / country recovery action","textarea","State target, actual, exact gap, owner, deadline and evidence required."],
      ["Marketing, CRM, AI and product issues","textarea","Lead-flow variance, data quality, automation failure and corrective action."],
      ["Executive support and tomorrow's priorities","textarea","Top opportunities, decisions required and next-day departmental result."]
    ];
    if(role==="BDM") return [
      ["Organization daily revenue target","number",Math.round(s.target/state.workingDays)],
      ["Actual cleared revenue today","number",Math.round(s.actual/state.elapsedDays)],
      ["SBUs at 80%+ pace","number",s.departments.filter(d=>paceStatus(d.actual,d.target).status==="green").length],
      ["Strategic-account meetings","number",4],
      ["BDM personal revenue MTD","number",s.personalActual],
      ["Consolidated qualified pipeline","number",s.pipeline],
      ["Proposals / tenders at risk","number",3],
      ["Collections requiring escalation","number",7],
      ["SBU performance summary","textarea",s.departments.map(d=>`${d.name}: ${shortMoney(d.actual)} / ${shortMoney(d.target)}; forecast ${shortMoney(d.forecast)}`).join("\n")],
      ["Strategic accounts and blocked deals","textarea","Account, value, stage, owner, blocker, executive action and next date."],
      ["HOD coaching / recovery decisions","textarea","Named HOD, issue, action, deadline and review point."],
      ["CEO decisions required","textarea","Budget, pricing, executive access, technology, legal, payment or capacity decision."]
    ];
    return [
      ["Organization target","number",s.target],
      ["Cleared revenue MTD","number",s.actual],
      ["Month-end forecast","number",s.forecast],
      ["Commission exposure","number",commissionForScope(s).current],
      ["SBUs below pace","number",s.departments.filter(d=>paceStatus(d.actual,d.target).status==="red").length],
      ["Staff requiring immediate support","number",s.departments.flatMap(d=>d.team).filter(x=>paceStatus(x.actual,x.target).status==="red").length],
      ["High-value opportunities requiring CEO","number",5],
      ["Critical collections / risks","number",6],
      ["Enterprise performance interpretation","textarea","What moved, why it moved, largest gap, strongest SBU and weakest SBU."],
      ["CEO intervention centre","textarea","Decision, responsible executive, deadline, expected commercial effect and follow-up date."],
      ["Recognition and leadership message","textarea","Recognize evidence-based performance and reinforce the next organization-wide priority."],
      ["Next executive review priorities","textarea","Revenue, collections, strategic accounts, products, staffing and decisions."]
    ];
  }

  function report(){
    const s=getScope(),fields=reportFields(state.role,s);
    return `
      <section class="panel">
        <div class="panel-head"><div><h3>${state.role==="BDE"?"BDE daily execution report":state.role==="BDO"?"BDO / HOD daily command report":state.role==="BDM"?"BDM consolidated commercial report":"CEO intervention and decision report"}</h3><p>The form is embedded in the dashboard. Numeric fields should prefill from CRM and Finance; the user explains causes, actions, decisions and learning.</p></div><span class="badge blue">Auto-prefilled</span></div>
        <div class="form-grid" id="reportForm">
          ${fields.map((f,i)=>`<div class="field ${f[1]==="textarea"?"span2":""}"><label for="rf${i}">${esc(f[0])}</label>${f[1]==="textarea"?`<textarea id="rf${i}" data-label="${esc(f[0])}">${esc(f[2])}</textarea>`:`<input class="input" id="rf${i}" data-label="${esc(f[0])}" type="${f[1]}" value="${esc(f[2])}">`}</div>`).join("")}
        </div>
        <div class="report-actions"><button class="primary-btn" id="generateReport" type="button">Generate report summary</button><button class="ghost-btn" id="downloadReport" type="button">Download report</button><button class="ghost-btn" id="clearNarrative" type="button">Clear narrative fields</button></div>
      </section>
      <section class="panel"><div class="panel-head"><div><h3>Generated management summary</h3><p>This preview can be stored as a daily snapshot, sent upward and compared with the next day's actual results.</p></div><span class="badge green">Evidence-linked</span></div><div id="reportPreview" class="report-preview">Select “Generate report summary” to compile the dashboard data and your explanations.</div></section>
      <section class="grid-3">
        ${[
          ["Automatic evidence","Revenue, payments, activity logs, opportunities, meetings, proposals, demos and CRM completeness are system-calculated."],
          ["Required human judgement","The user explains why performance moved, what is blocked, what was learned and which support or decision is required."],
          ["Manager workflow","Supervisor reviews, comments, approves or returns the report and converts commitments into tracked actions."]
        ].map(([a,b])=>`<div class="panel"><div class="panel-head"><div><h3>${a}</h3></div></div><p style="font-size:12px;color:var(--muted);margin:0">${b}</p></div>`).join("")}
      </section>`;
  }

  function strategy(){
    const d=state.role==="BDE"||state.role==="BDO"?departments[state.department]:departments["Corporate"];
    const s=getScope();
    const scorecards = state.role==="BDE"
      ? [["Revenue / qualifying volume",35],["Pipeline and conversion",20],["CRM and forecast quality",15],["Strategic execution",10],["Marketing / channel learning",10],["Client experience and reporting",10]]
      : state.role==="BDO"
      ? [["Departmental revenue and collections",30],["Balanced team performance",20],["Pipeline and forecast control",15],["Coaching and recovery",15],["CRM / AI / reporting",10],["Market and product leadership",10]]
      : state.role==="BDM"
      ? [["Revenue achievement across SBUs",30],["Qualified pipeline",10],["Proposal and tender conversion",10],["Strategic accounts",10],["Team supervision",10],["CRM / ERP discipline",10],["Marketing conversion",5],["Collections",5],["Reporting",5],["Systems improvement",5]]
      : [["Organization revenue and forecast",30],["Strategic growth and market position",15],["SBU balance and leadership",15],["Cash, margin and recurring revenue",15],["Critical accounts and partnerships",10],["Product and customer experience",5],["People and execution culture",5],["Governance and decisions",5]];
    return `
      <section class="panel"><div class="panel-head"><div><h3>Role mandate</h3><p>The dashboard should remind the user what the role exists to achieve—not only display numbers.</p></div><span class="badge blue">${esc(roleMandate())}</span></div><div class="callout"><strong>${esc(s.strategyTitle)}</strong><br>${esc(s.strategyText)}</div></section>
      <section class="panel"><div class="panel-head"><div><h3>Non-negotiable operating principles</h3><p>These appear as short reminders and are also used by supervisors during coaching and report review.</p></div></div><div class="principles">${d.principles.map(([a,b])=>`<div class="principle"><b>${esc(a)}</b><p>${esc(b)}</p></div>`).join("")}</div></section>
      <section class="grid-2">
        <div class="panel"><div class="panel-head"><div><h3>Daily operating rhythm</h3><p>The dashboard changes emphasis throughout the day: morning priorities, mid-day pace review and closing actions.</p></div></div><div class="timeline">${d.dailyRhythm.map(([t,x])=>`<div class="time-row"><time>${esc(t)}</time><div>${esc(x)}</div></div>`).join("")}</div></div>
        <div class="panel"><div class="panel-head"><div><h3>Performance scorecard</h3><p>Target achievement is central, but quality, balance, CRM evidence, learning and client experience remain visible.</p></div></div>
          ${scorecards.map(([n,w])=>`<div class="source-row" style="grid-template-columns:190px 1fr 45px"><span>${esc(n)}</span><div class="bar"><div style="width:${w/35*100}%"></div></div><b>${w}%</b></div>`).join("")}
        </div>
      </section>
      <section class="grid-3">
        <div class="panel"><div class="panel-head"><div><h3>Green response</h3></div><span class="badge green">At / above pace</span></div><p style="font-size:12px;color:var(--muted)">Protect quality, collections and client experience; pursue stretch opportunities and share winning practices.</p></div>
        <div class="panel"><div class="panel-head"><div><h3>Amber response</h3></div><span class="badge amber">Near pace / weak movement</span></div><p style="font-size:12px;color:var(--muted)">Agree corrective action within 24 hours, intensify senior support and concentrate on the nearest commercial next steps.</p></div>
        <div class="panel"><div class="panel-head"><div><h3>Red response</h3></div><span class="badge red">Below pace / major risk</span></div><p style="font-size:12px;color:var(--muted)">Create a quantified recovery plan, monitor daily and escalate decisions or resources before the gap becomes irreversible.</p></div>
      </section>`;
  }

  function developer(){
    return `
      <section class="panel"><div class="panel-head"><div><h3>Recommended technical architecture</h3><p>One formula and rule service should feed web dashboards, reports, alerts and commission calculations.</p></div><span class="badge blue">Implementation map</span></div>
        <div class="dev-grid">
          ${[
            ["Identity and hierarchy","Stable <code>staff_id</code>, role code, SBU, reporting line, active status and effective dates."],
            ["Targets and rule master","Effective-dated targets, fees, volume rules, commission bands, gates, hold-backs and approval versions."],
            ["Lead and opportunity","Source, ownership, stage, value, probability, buying group, next action, due date and activity history."],
            ["Client and transaction","Organization, individual, enrollment, subscription, staff activation, country, product, course and unique transaction key."],
            ["Finance and commission","Invoices, amount due, cleared payment, reversals, collection rate, acquisition owner and calculation trace."],
            ["Performance snapshots","Real-time current state plus locked nightly and month-end snapshots for history, audit and trend comparison."],
            ["Alerts and action centre","Event-driven alerts with exact gap, affected record, owner, required action, due date and acknowledgement."],
            ["Reporting workflow","Auto-prefilled report, human narrative, supervisor comment, approval/return, action conversion and archived version."]
          ].map(([a,b])=>`<div class="panel" style="box-shadow:none"><div class="panel-head"><div><h3>${a}</h3></div></div><p style="font-size:12px;color:var(--muted);margin:0">${b}</p></div>`).join("")}
        </div>
      </section>
      <section class="grid-2">
        <div class="panel"><div class="panel-head"><div><h3>Role permissions</h3><p>Scope expands by hierarchy while sensitive Finance and HR actions remain restricted.</p></div></div>
          <div class="table-wrap"><table><thead><tr><th>Role</th><th>Read scope</th><th>Core actions</th><th>Restrictions</th></tr></thead><tbody>
            <tr><td><b>BDE / Coordinator</b></td><td>Own records and portfolio</td><td>Create/update leads, activities, reports, escalations</td><td>Cannot approve commission or alter cleared payments</td></tr>
            <tr><td><b>BDO / HOD</b></td><td>Own department and personal portfolio</td><td>Assign, coach, comment, recover, escalate, approve reports</td><td>Cannot change approved rule versions or Finance evidence</td></tr>
            <tr><td><b>BDM</b></td><td>All commercial SBUs</td><td>Reallocate support, control forecasts, strategic accounts and HOD performance</td><td>Commission approval remains authorized workflow</td></tr>
            <tr><td><b>CEO / Director</b></td><td>Enterprise view</td><td>Executive decisions, intervention, strategic approval and recognition</td><td>Audit trail preserved; no silent data overwrite</td></tr>
            <tr><td><b>Finance verifier</b></td><td>Payment and commission evidence</td><td>Post/reverse payment, verify collections and payable commission</td><td>Cannot reassign commercial ownership without approval</td></tr>
          </tbody></table></div>
        </div>
        <div class="panel"><div class="panel-head"><div><h3>Core formulas and events</h3><p>Production logic should be centralized and testable.</p></div></div>
          <div class="timeline">
            <div class="time-row"><time>Achievement</time><div><code>actual_qualifying_value ÷ approved_target</code></div></div>
            <div class="time-row"><time>Pace</time><div><code>actual ÷ (target × elapsed_working_days ÷ total_working_days)</code></div></div>
            <div class="time-row"><time>Forecast</time><div>Weighted pipeline by expected close date + cleared actual, with manual override reason and audit.</div></div>
            <div class="time-row"><time>Commission</time><div>Rule version + qualifying transactions + cleared payments + gates − reversals and approved hold-backs.</div></div>
            <div class="time-row"><time>Alert events</time><div>Lead created, stage change, action overdue, payment posted, payment reversed, threshold crossed, campaign below pace, issue escalated.</div></div>
            <div class="time-row"><time>Snapshots</time><div>Near-real-time UI, nightly performance snapshot and locked month-end calculation snapshot.</div></div>
          </div>
        </div>
      </section>
      <section class="panel"><div class="panel-head"><div><h3>Suggested API payload for the role dashboard</h3><p>The front-end should receive display-ready metrics plus drill-down identifiers and calculation explanations.</p></div></div>
        <div class="report-preview">{
  "context": {"month":"2026-09","role":"BDE","staff_id":"DIG-EVAL-01","sbu_id":"DIGITAL"},
  "targets": [{"kpi_code":"EVAL_CORP_SETUP","volume_target":2,"revenue_target":1800000}],
  "actuals": {"cleared_revenue":1370000,"qualifying_volume":84,"collection_percent":0.95},
  "pace": {"expected_to_date":1270455,"pace_ratio":1.078,"status":"GREEN"},
  "pipeline": {"qualified_value":8900000,"coverage_ratio":4.14,"overdue_actions":3},
  "commission": {"estimated":29500,"payable":0,"held":29500,"rule_version":"COMM-2026-09-v1",
                 "explanation":["Corporate support threshold not complete"]},
  "actions": [{"priority":"HIGH","record_id":"OPP-1042","instruction":"Confirm demo decision and payment route","due_at":"2026-09-15T10:00:00+03:00"}],
  "report": {"prefilled":true,"narrative_required":["what_worked","blockers","support_required","tomorrow_plan"]}
}</div>
      </section>
      <section class="callout"><strong>Important:</strong> Performance-support and PIP alerts should never impose an employment decision automatically. The CRM surfaces evidence, history and recommended workflow; authorized leaders make and document the decision under company policy.</section>`;
  }

  function render(){
    updateStrategy();
    const w=document.getElementById("workspace");
    w.innerHTML=state.view==="overview"?overview():state.view==="pipeline"?pipeline():state.view==="commission"?commission():state.view==="report"?report():state.view==="strategy"?strategy():developer();
    bindDynamic();
  }

  function bindDynamic(){
    document.querySelectorAll(".scenario-input").forEach(inp=>inp.addEventListener("change",()=>{
      const key=inp.dataset.key;
      state.overrides[scopeKey()] ||= {};
      let v=Number(inp.value);
      if(key==="collection")v=v/100;
      state.overrides[scopeKey()][key]=v;
      render();
    }));
    const reset=document.getElementById("resetScenario");
    if(reset)reset.addEventListener("click",()=>{delete state.overrides[scopeKey()];render()});
    const gen=document.getElementById("generateReport");
    if(gen)gen.addEventListener("click",generateReport);
    const down=document.getElementById("downloadReport");
    if(down)down.addEventListener("click",downloadReport);
    const clear=document.getElementById("clearNarrative");
    if(clear)clear.addEventListener("click",()=>document.querySelectorAll("#reportForm textarea").forEach(x=>x.value=""));
  }

  function generateReport(){
    const s=getScope(),lines=[];
    lines.push(`VANTAGE AFRICA — ${state.role} PERFORMANCE REPORT`);
    lines.push(`Period: ${document.getElementById("monthSelect").value}`);
    lines.push(`Role holder: ${s.name} | ${s.title}`);
    lines.push(`Scope: ${s.department}`);
    lines.push("");
    document.querySelectorAll("#reportForm input,#reportForm textarea").forEach(el=>{
      lines.push(`${el.dataset.label}: ${el.value.trim()||"—"}`);
    });
    lines.push("");
    lines.push(`Dashboard position: ${shortMoney(s.actual)} cleared against ${shortMoney(s.target)} target (${pct(s.actual/s.target)}).`);
    lines.push(`Qualified pipeline: ${shortMoney(s.pipeline)}. Collection rate: ${pct(s.collection)}.`);
    lines.push(`Commission / incentive estimate: ${shortMoney(commissionForScope(s).current)}.`);
    lines.push("All figures are subject to CRM evidence and Finance verification.");
    const p=document.getElementById("reportPreview");p.textContent=lines.join("\n");p.dataset.report=lines.join("\n");
  }

  function downloadReport(){
    const p=document.getElementById("reportPreview");
    if(!p.dataset.report)generateReport();
    const text=document.getElementById("reportPreview").dataset.report||document.getElementById("reportPreview").textContent;
    const blob=new Blob([text],{type:"text/plain"});
    const a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download=`Vantage_${state.role}_${document.getElementById("monthSelect").value.replace(/\s+/g,"_")}_Report.txt`;a.click();URL.revokeObjectURL(a.href);
  }

  // Period stays interactive; Role/Department/Employee are locked (display-only).
  document.getElementById("monthSelect").addEventListener("change",render);
  document.querySelectorAll(".tab").forEach(b=>b.addEventListener("click",()=>{state.view=b.dataset.view;document.querySelectorAll(".tab").forEach(x=>x.classList.toggle("active",x===b));render()}));
  document.getElementById("themeBtn").addEventListener("click",()=>{
    state.theme=state.theme==="light"?"dark":"light";document.documentElement.dataset.theme=state.theme;
    document.getElementById("themeBtn").textContent=state.theme==="light"?"Dark mode":"Light mode";
  });
  document.getElementById("printBtn").addEventListener("click",()=>window.print());

  render();
})();
</script>
</body>
</html>
