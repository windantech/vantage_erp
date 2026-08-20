<?php
// bde_dashboard.php  (admin/bde_dashboard.php)
// Private BDE performance dashboard — phase 1 (illustrative / dummy data).
//
// Uses the SAME chrome as the enquiry dashboard: the root header.php (its left
// nav), top_nav.php and footer.php. The dashboard's own design system (ported
// from the v11 prototype, recoloured to a blue theme) is scoped under a single
// `.bde-app` container so it neither leaks into nor is overridden by the admin
// Bootstrap styles. The theme toggle flips a class on that container only.
session_start();
require_once 'header.php';   // enquiry/admin left nav + chrome + $conn
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); }
require_once 'includes/bde_metrics.php';

// Who are we viewing? The BDM is Michael #127. Admins can preview via ?as=<id>; everyone else
// sees their own login. Default to Michael so the page always has a subject while it's new.
$bdm_is_admin = isset($role) && is_array($role) && in_array(777, $role);
$bdm_id = (int) ($_SESSION['login_id'] ?? 0);
if ($bdm_id <= 0) { $bdm_id = 127; }
if (isset($_GET['as']) && $bdm_is_admin) { $bdm_id = (int) $_GET['as']; }

$bdm_from = date('Y-m-01');
$bdm_to   = date('Y-m-d');
$bdm = bdm_rollup($conn, $bdm_from, $bdm_to, $bdm_id);

// Admin "view as" roster for the BDM page: the super-user plus the BDM (Michael).
$bdm_people = [['id' => 127, 'name' => 'Michael Obworo Mongere', 'role' => 'BDM']];
?>
<section id="content-wrapper" class="d-flex flex-column">
  <div id="content">
    <?php require_once 'top_nav.php'; ?>

    <style>
    /* ===== BDE dashboard — all scoped under .bde-app (blue theme) ===== */
    .bde-app{
      --ground:#e9eef3; --surface:#ffffff; --surface2:#f3f6f9; --surface3:#e7edf2;
      --ink:#151d28; --ink2:#3b4756; --muted:#6a7886; --faint:#9aa8b5; --line:#dce4eb;
      /* status accent = green; brand action accent = orange; theme base = blue */
      --jade:#0e9e79; --jade-deep:#0a7a5e; --jade-soft:#e2f4ee;
      --brand:#ec6e2d; --brand-deep:#c85a1e; --brand-soft:#fdece1;
      --gold:#c98a1c; --gold-soft:#fbf0d8; --gold-line:#eecf94; --amber:#c67e12; --amber-soft:#fbeed6;
      --coral:#d6472f; --coral-soft:#fbe4df; --slate:#4f6f9c; --slate-soft:#e8eef6; --violet:#6f5fbf; --violet-soft:#efeafb;
      --sidebar1:#14232f; --sidebar2:#0c141c;
      --shadow:0 1px 2px rgba(21,29,40,.05),0 14px 30px rgba(21,29,40,.09); --shadow-sm:0 1px 2px rgba(21,29,40,.06),0 4px 12px rgba(21,29,40,.05);
      --radius:16px; --radius-sm:11px;
      background:var(--ground);color:var(--ink);font-size:14px;
      font-family:ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
      line-height:1.45;-webkit-font-smoothing:antialiased;
      max-width:none;margin:0;padding:80px 24px 44px;border-radius:0;width:100%;min-height:100vh;
    }
    .bde-app.theme-dark{
      --ground:#0c1219; --surface:#161f2a; --surface2:#1d2833; --surface3:#212e3a; --ink:#eef3f7; --ink2:#c2cdd8; --muted:#8b9aa9; --faint:#63727f; --line:#28343f;
      --jade:#2ec39a; --jade-deep:#41d3ab; --jade-soft:#123027;
      --brand:#f2905a; --brand-deep:#e07640; --brand-soft:#2c1c12;
      --gold:#e2b158; --gold-soft:#2c2413; --gold-line:#4a3d1d; --amber:#e0a343; --amber-soft:#2c2413;
      --coral:#f0715a; --coral-soft:#331a16; --slate:#7d9dcb; --slate-soft:#182533; --violet:#9f90e0; --violet-soft:#20203a; --sidebar1:#111b25; --sidebar2:#0a1017;
      --shadow:0 1px 2px rgba(0,0,0,.32),0 18px 36px rgba(0,0,0,.4); --shadow-sm:0 1px 2px rgba(0,0,0,.3),0 6px 16px rgba(0,0,0,.3);
    }
    .bde-app .num{font-variant-numeric:tabular-nums;font-feature-settings:"tnum" 1}
    .bde-app *{box-sizing:border-box}
    .bde-app button,.bde-app input,.bde-app select,.bde-app textarea{font:inherit;color:inherit}
    .bde-app button{cursor:pointer} .bde-app [hidden]{display:none!important}

    .bde-app .bde-topbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px 18px}
    .bde-app .brand{display:flex;align-items:center;gap:12px}
    .bde-app .brand .mark{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(150deg,var(--brand),var(--gold));color:#fff;font-weight:800;font-size:16px;letter-spacing:-.5px;box-shadow:0 8px 18px rgba(236,110,45,.35)}
    .bde-app .brand h1{font-size:16px;margin:0;letter-spacing:-.01em} .bde-app .brand p{font-size:11.5px;color:var(--muted);margin:2px 0 0}
    .bde-app .controls{margin-left:auto;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
    .bde-app .control{display:grid;gap:4px}
    .bde-app .control label{font-size:9.5px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:800}
    .bde-app .control select{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:8px 28px 8px 11px;font-size:13px;font-weight:650;appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--muted) 50%),linear-gradient(135deg,var(--muted) 50%,transparent 50%);background-position:calc(100% - 15px) 16px,calc(100% - 10px) 16px;background-size:5px 5px;background-repeat:no-repeat}
    .bde-app .control select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
    .bde-app .tbtn{border:1px solid var(--line);background:var(--surface2);border-radius:10px;padding:9px 13px;font-size:13px;font-weight:650;display:inline-flex;align-items:center;gap:7px;color:var(--ink)} .bde-app .tbtn:hover{border-color:var(--brand);color:var(--brand)}
    .bde-app .tbtn.solid{background:var(--brand);color:#fff;border-color:var(--brand)} .bde-app .tbtn.solid:hover{background:var(--brand-deep);color:#fff}
    .bde-app .profile-chip{display:flex;align-items:center;gap:10px;padding:5px 13px 5px 5px;border:1px solid var(--line);border-radius:12px;background:var(--surface2)}
    .bde-app .profile-chip .a{width:34px;height:34px;border-radius:9px;background:linear-gradient(150deg,var(--slate),#33507a);color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px}
    .bde-app .profile-chip b{font-size:13px;display:block;line-height:1.15} .bde-app .profile-chip span{font-size:11px;color:var(--muted)}
    .bde-app .tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0 2px}
    .bde-app .tab{border:1px solid var(--line);background:var(--surface);border-radius:11px;padding:10px 15px;font-size:13px;font-weight:700;color:var(--muted);box-shadow:var(--shadow-sm);display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:color .15s,border-color .15s}
    .bde-app .tab svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .tab:hover{color:var(--ink);border-color:var(--brand)}
    .bde-app .tab.active{background:linear-gradient(120deg,var(--brand),var(--brand-deep));color:#fff;border-color:var(--brand);box-shadow:0 8px 18px rgba(236,110,45,.3)}
    .bde-app #workspace{display:grid;gap:18px;margin-top:18px}
    .bde-app .section-tag{display:flex;align-items:center;gap:12px;margin:8px 2px 0}
    .bde-app .section-tag h3{margin:0;font-size:16px;letter-spacing:-.01em} .bde-app .section-tag>span{font-size:12.5px;color:var(--muted)} .bde-app .section-tag .rule{flex:1;height:1px;background:linear-gradient(90deg,var(--line),transparent)}

    .bde-app .strategy{border-radius:var(--radius);background:linear-gradient(120deg,var(--sidebar1),#1c3a52);color:#fff;padding:20px 22px;display:grid;grid-template-columns:minmax(0,1.5fr) minmax(240px,.7fr);gap:18px;align-items:center;box-shadow:var(--shadow)}
    .bde-app .strategy .eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:.16em;color:#9fd0ea;font-weight:800}
    .bde-app .strategy h2{font-size:20px;margin:6px 0 6px;letter-spacing:-.01em;line-height:1.25;color:#fff} .bde-app .strategy p{margin:0;font-size:12.5px;color:rgba(255,255,255,.82);line-height:1.5}
    .bde-app .strategy .focus{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:13px;padding:13px 15px} .bde-app .strategy .focus b{display:block;color:#ffd9a8;font-size:11px;text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px} .bde-app .strategy .focus span{font-size:12.5px;color:rgba(255,255,255,.9);line-height:1.5}

    .bde-app .card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px} .bde-app .card.tight{padding:14px}
    .bde-app .card h4{margin:0;font-size:15px;letter-spacing:-.01em;color:var(--ink)} .bde-app .card .sub{font-size:12px;color:var(--muted);margin:2px 0 0}
    .bde-app .chead{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:15px}
    .bde-app .chip{font-size:11px;font-weight:800;letter-spacing:.04em;padding:5px 11px;border-radius:999px;white-space:nowrap;align-self:center}
    .bde-app .chip.jade{color:var(--jade);background:var(--jade-soft)} .bde-app .chip.gold{color:var(--gold);background:var(--gold-soft)} .bde-app .chip.slate{color:var(--slate);background:var(--slate-soft)} .bde-app .chip.amber{color:var(--amber);background:var(--amber-soft)} .bde-app .chip.coral{color:var(--coral);background:var(--coral-soft)}
    .bde-app .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px} .bde-app .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    .bde-app .hero{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);gap:16px}
    .bde-app .pace-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;font-weight:750;font-size:12px;border:1px solid} .bde-app .pace-pill .dot{width:8px;height:8px;border-radius:50%}
    .bde-app .pg{color:var(--jade);background:var(--jade-soft);border-color:color-mix(in srgb,var(--jade) 30%,transparent)} .bde-app .pg .dot{background:var(--jade)}
    .bde-app .pa{color:var(--amber);background:var(--amber-soft);border-color:color-mix(in srgb,var(--amber) 32%,transparent)} .bde-app .pa .dot{background:var(--amber)}
    .bde-app .pr{color:var(--coral);background:var(--coral-soft);border-color:color-mix(in srgb,var(--coral) 32%,transparent)} .bde-app .pr .dot{background:var(--coral)}

    .bde-app .kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .bde-app .kpi{position:relative;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:15px;overflow:hidden;transition:transform .15s,box-shadow .15s}
    .bde-app .kpi:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
    .bde-app .kpi::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:var(--brand);border-radius:var(--radius-sm) var(--radius-sm) 0 0}
    .bde-app .kpi .kicon{position:absolute;top:14px;right:14px;width:28px;height:28px;border-radius:8px;display:grid;place-items:center;background:var(--brand-soft);color:var(--brand)} .bde-app .kpi .kicon svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .kpi .lab{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);font-weight:800;padding-right:34px}
    .bde-app .kpi .val{font-size:24px;font-weight:850;letter-spacing:-.02em;margin:10px 0 3px;line-height:1} .bde-app .kpi .meta{font-size:12px;color:var(--muted)}
    .bde-app .kpi .delta{font-size:11px;font-weight:700;margin-top:10px} .bde-app .kpi .delta .dic{font-weight:900;font-size:14px;display:inline-block;vertical-align:-1px;margin-right:1px} .bde-app .delta.up{color:var(--jade)} .bde-app .delta.down{color:var(--coral)} .bde-app .delta.flat{color:var(--brand)}

    .bde-app .prog .pl{font-size:13px;color:var(--muted);margin-top:2px} .bde-app .prog .pl b{color:var(--ink)}
    .bde-app .bar{height:14px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden;margin-top:14px;position:relative} .bde-app .bar .bf{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--coral),var(--amber) 55%,var(--jade));transition:width .6s cubic-bezier(.22,.61,.36,1)} .bde-app .bar .exp{position:absolute;top:-4px;bottom:-4px;width:2px;background:var(--ink2);opacity:.6}
    .bde-app .mini3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:15px}
    .bde-app .cm{background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px} .bde-app .cm span{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800} .bde-app .cm b{display:block;font-size:18px;font-weight:850;margin-top:5px;letter-spacing:-.02em} .bde-app .cm.gold b{color:var(--gold)}
    .bde-app .motiv{margin-top:15px;border-radius:var(--radius-sm);padding:14px;font-size:13px;line-height:1.5} .bde-app .motiv b{font-weight:800} .bde-app .chead + .motiv{margin-top:0}
    .bde-app .motiv.green{background:var(--slate-soft);color:var(--ink2);border:1px solid color-mix(in srgb,var(--slate) 30%,var(--line))} .bde-app .motiv.green b{color:var(--slate)}
    .bde-app .motiv.amber{background:var(--amber-soft);color:var(--ink2);border:1px solid var(--gold-line)} .bde-app .motiv.amber b{color:var(--amber)}
    .bde-app .motiv.red{background:var(--coral-soft);color:var(--ink2);border:1px solid color-mix(in srgb,var(--coral) 30%,var(--line))} .bde-app .motiv.red b{color:var(--coral)}

    .bde-app .chart{width:100%;height:200px;display:block} .bde-app .chart text{fill:var(--muted);font-size:10.5px} .bde-app .chart .grid{stroke:var(--line);stroke-width:1} .bde-app .chart .tline{stroke:var(--brand);stroke-dasharray:5 5;stroke-width:1.5} .bde-app .chart .area{fill:color-mix(in srgb,var(--brand) 14%,transparent)} .bde-app .chart .line{fill:none;stroke:var(--brand);stroke-width:3} .bde-app .chart .dot{fill:var(--surface);stroke:var(--brand);stroke-width:2}

    .bde-app .road-wrap{position:relative;margin:12px 4px 32px} .bde-app .road{height:16px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .road .rf{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--coral),var(--amber) 55%,var(--jade));transition:width .6s cubic-bezier(.22,.61,.36,1)}
    .bde-app .rmark{position:absolute;top:-2px;transform:translateX(-50%);text-align:center} .bde-app .rmark i{display:block;width:2px;height:22px;background:var(--faint);margin:0 auto;border-radius:2px} .bde-app .rmark span{font-size:10px;font-weight:800;color:var(--muted);margin-top:2px;display:block}
    .bde-app .nextstep{margin-top:12px;background:var(--slate-soft);border:1px solid color-mix(in srgb,var(--slate) 30%,var(--line));border-radius:var(--radius-sm);padding:13px;font-size:12.5px;color:var(--ink2);line-height:1.5} .bde-app .nextstep b{color:var(--slate)}

    .bde-app .list{display:grid;gap:9px}
    .bde-app .arow{display:grid;grid-template-columns:auto 1fr auto;gap:11px;align-items:start;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px}
    .bde-app .arow .pd{width:8px;height:8px;border-radius:50%;margin-top:6px;align-self:start} .bde-app .arow b{font-size:12.5px}.bde-app .arow p{margin:2px 0 0;font-size:11.5px;color:var(--muted)}
    .bde-app .arow .due{font-size:10px;font-weight:800;color:var(--muted);white-space:nowrap;background:var(--surface3);padding:4px 8px;border-radius:7px;border:1px solid var(--line);align-self:center}
    .bde-app .stage-chip{display:inline-block;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:8px;background:var(--slate-soft);color:var(--slate)}
    .bde-app .duec{display:inline-block;font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:8px}
    .bde-app .duec.hot{background:var(--coral-soft);color:var(--coral)} .bde-app .duec.soon{background:var(--amber-soft);color:var(--amber)} .bde-app .duec.cool{background:var(--slate-soft);color:var(--slate)}
    .bde-app .arow .abtn{align-self:center;font-size:10.5px;font-weight:800;padding:5px 12px;border-radius:8px;white-space:nowrap;border:0;cursor:pointer;transition:background .15s,color .15s}
    .bde-app .abtn.hot{background:var(--coral-soft);color:var(--coral)} .bde-app .abtn.hot:hover{background:var(--coral);color:#fff}
    .bde-app .abtn.warn{background:var(--amber-soft);color:var(--amber)} .bde-app .abtn.warn:hover{background:var(--amber);color:#fff}
    .bde-app .abtn.info{background:var(--slate-soft);color:var(--slate)} .bde-app .abtn.info:hover{background:var(--slate);color:#fff}
    .bde-app .table-wrap tbody tr:not(.me):hover td{background:color-mix(in srgb,var(--slate) 6%,var(--surface))}
    .bde-app .segmented{display:inline-flex;border:1px solid var(--line);border-radius:9px;overflow:hidden;background:var(--surface2)} .bde-app .seg{border:0;background:transparent;padding:6px 12px;font-size:11px;font-weight:750;color:var(--muted);cursor:pointer} .bde-app .seg.on{background:var(--brand);color:#fff}
    .bde-app .legend{display:flex;gap:16px;margin-top:10px;font-size:11.5px;color:var(--muted);font-weight:700} .bde-app .lg{display:inline-flex;align-items:center;gap:6px} .bde-app .lg i{width:11px;height:11px;border-radius:3px;display:inline-block}
    .bde-app .tvp-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px} .bde-app .tvp{background:var(--surface2);border:1px solid var(--line);border-radius:11px;padding:13px} .bde-app .tvp-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px;gap:8px} .bde-app .tvp-top b{font-size:12.5px} .bde-app .tvp-sub{font-size:10.5px;color:var(--muted);margin-top:7px;font-variant-numeric:tabular-nums}
    .bde-app .track2{height:9px;border-radius:6px;background:var(--surface3);overflow:hidden;border:1px solid var(--line)} .bde-app .fill2{height:100%;border-radius:6px}
    .bde-app .pd.red{background:var(--coral)}.bde-app .pd.amber{background:var(--amber)}.bde-app .pd.blue{background:var(--slate)}.bde-app .pd.green{background:var(--jade)}

    .bde-app .drivers{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .bde-app .driver{position:relative;overflow:hidden;background:color-mix(in srgb,var(--dacc,var(--brand)) 10%,var(--surface));border:0;border-radius:var(--radius-sm);padding:15px 15px 14px;display:flex;flex-direction:column;box-shadow:0 1px 3px rgba(16,40,64,.05);transition:transform .15s,box-shadow .15s}
    .bde-app .driver:hover{transform:translateY(-2px);box-shadow:0 8px 16px -10px rgba(16,40,64,.20)}
    .bde-app .driver .dtop{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
    .bde-app .driver .dicon{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;background:var(--surface);color:var(--dacc,var(--brand));box-shadow:0 1px 2px rgba(16,40,64,.06)}
    .bde-app .driver .dicon svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .driver .n{font-size:23px;font-weight:850;margin:1px 0;letter-spacing:-.02em;color:var(--ink)} .bde-app .driver b{font-size:13px;color:var(--ink)} .bde-app .driver small{color:var(--muted);font-size:11px;margin-top:1px}
    .bde-app .live{font-size:9px;font-weight:800;color:var(--jade);background:var(--jade-soft);padding:2px 6px;border-radius:5px;text-transform:uppercase;letter-spacing:.05em}

    .bde-app .funnel{display:grid;gap:10px} .bde-app .fr{display:grid;grid-template-columns:170px 1fr 60px;gap:11px;align-items:center} .bde-app .fr label{font-size:12px;font-weight:650}
    .bde-app .fbar{height:28px;background:var(--surface3);border:1px solid var(--line);border-radius:8px;overflow:hidden} .bde-app .fbar div{height:100%;background:linear-gradient(90deg,#2f5f9e,#4d8bd6);border-radius:8px;display:flex;align-items:center;padding-left:12px;color:#fff;font-size:11px;font-weight:800;font-variant-numeric:tabular-nums;box-shadow:0 1px 4px -1px rgba(47,95,158,.5);transition:width .5s ease} .bde-app .fr .cv{justify-self:end;background:var(--slate-soft);color:var(--slate);font-size:10.5px;font-weight:800;padding:3px 8px;border-radius:999px;font-variant-numeric:tabular-nums}
    .bde-app .src{display:grid;grid-template-columns:1fr 100px auto;gap:11px;align-items:center;padding:7px 0} .bde-app .src label{font-size:12px;font-weight:600} .bde-app .src .sb{height:10px;border-radius:6px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .src .sb div{height:100%;border-radius:6px;background:linear-gradient(90deg,#2f5f9e,#4d8bd6)} .bde-app .src b{justify-self:end;background:var(--slate-soft);color:var(--slate);font-size:10.5px;font-weight:800;padding:3px 8px;border-radius:999px;font-variant-numeric:tabular-nums}

    .bde-app .table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:var(--radius-sm)}
    .bde-app table{width:100%;border-collapse:collapse;min-width:720px;background:var(--surface)} .bde-app th,.bde-app td{text-align:left;padding:13px 15px;border-bottom:1px solid var(--line);font-size:12.5px;vertical-align:middle}
    .bde-app th{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);background:var(--surface2);font-weight:800} .bde-app tr:last-child td{border-bottom:0}
    .bde-app .prow{display:flex;align-items:center;gap:10px} .bde-app .prow .a{width:30px;height:30px;border-radius:8px;background:var(--slate);color:#fff;display:grid;place-items:center;font-size:10px;font-weight:850} .bde-app .prow b{display:block;font-size:12.5px}.bde-app .prow span{font-size:10.5px;color:var(--muted)}
    .bde-app tr.me{background:linear-gradient(90deg,color-mix(in srgb,#3a7bd5 16%,var(--surface)),color-mix(in srgb,#3a7bd5 5%,var(--surface)))} .bde-app tr.me td{background:transparent} .bde-app tr.me .a{background:linear-gradient(150deg,#3a7bd5,#2a5aa8)}
    .bde-app .mini-track{height:7px;border-radius:99px;background:var(--surface3);overflow:hidden;border:1px solid var(--line);min-width:70px;display:inline-block;vertical-align:middle} .bde-app .mini-track div{height:100%;border-radius:99px}
    .bde-app .sbadge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;padding:4px 9px;border-radius:999px} .bde-app .sbadge .dot{width:7px;height:7px;border-radius:50%}
    .bde-app .sg{color:var(--jade);background:var(--jade-soft)} .bde-app .sg .dot{background:var(--jade)} .bde-app .sa{color:var(--amber);background:var(--amber-soft)} .bde-app .sa .dot{background:var(--amber)} .bde-app .sr{color:var(--coral);background:var(--coral-soft)} .bde-app .sr .dot{background:var(--coral)}

    .bde-app .check{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px} .bde-app .check .sym{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-size:14px;font-weight:900} .bde-app .check.ok .sym{background:var(--jade-soft);color:var(--jade)} .bde-app .check.no .sym{background:var(--coral-soft);color:var(--coral)} .bde-app .check b{font-size:12.5px} .bde-app .check small{font-size:10.5px;color:var(--muted);display:block;margin-top:1px} .bde-app .check .cv{font-size:13px;font-weight:850;font-variant-numeric:tabular-nums}
    .bde-app .audit{display:grid;grid-template-columns:auto 1fr;gap:11px;align-items:start;padding:11px 0;border-bottom:1px solid var(--line)} .bde-app .audit:last-child{border-bottom:0} .bde-app .audit .k{width:9px;height:9px;border-radius:50%;background:var(--slate);margin-top:5px} .bde-app .audit b{font-size:12.5px} .bde-app .audit p{margin:2px 0 0;font-size:11.5px;color:var(--muted)}
    .bde-app .steps3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px} .bde-app .stepbox{background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:14px} .bde-app .stepbox span{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800} .bde-app .stepbox b{display:block;font-size:15px;margin:6px 0 4px} .bde-app .stepbox .st{font-size:11.5px;font-weight:700}

    .bde-app .timeline{display:grid;gap:2px} .bde-app .time-row{display:grid;grid-template-columns:120px 1fr;gap:14px;padding:12px 0;border-bottom:1px solid var(--line)} .bde-app .time-row:last-child{border-bottom:0} .bde-app .time-row time{font-size:12px;font-weight:850;color:var(--brand)} .bde-app .time-row div{font-size:12.5px;color:var(--ink2)}
    .bde-app .principles{display:grid;grid-template-columns:repeat(3,1fr);gap:11px} .bde-app .principle{border-left:3px solid var(--brand);background:var(--surface2);border-radius:var(--radius-sm);padding:13px 15px} .bde-app .principle b{font-size:12.5px} .bde-app .principle p{font-size:11.5px;color:var(--muted);margin:4px 0 0;line-height:1.5}
    .bde-app .scorecard{display:grid;gap:11px} .bde-app .scr{display:grid;grid-template-columns:220px 1fr 48px;gap:12px;align-items:center} .bde-app .scr label{font-size:12px;font-weight:600} .bde-app .scr .sb{height:9px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .scr .sb div{height:100%;border-radius:99px;background:linear-gradient(90deg,#2f5f9e,#4d8bd6)} .bde-app .scr b{font-size:12.5px;font-weight:800;text-align:right}

    .bde-app .form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px} .bde-app .field{display:grid;gap:5px} .bde-app .field.span2{grid-column:span 2}.bde-app .field.span4{grid-column:span 4}
    .bde-app .field label{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800}
    .bde-app .field input,.bde-app .field textarea{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-size:13px;width:100%} .bde-app .field textarea{min-height:82px;resize:vertical;line-height:1.5} .bde-app .field input:focus,.bde-app .field textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)} .bde-app .field input:hover,.bde-app .field textarea:hover{border-color:color-mix(in srgb,var(--brand) 35%,var(--line))} .bde-app .field input[type=number]{font-variant-numeric:tabular-nums;font-weight:650}
    .bde-app .form-sub{display:flex;align-items:center;gap:10px;margin:2px 2px 11px;font-size:10px;text-transform:uppercase;letter-spacing:.14em;color:var(--muted);font-weight:800} .bde-app .form-sub i{color:var(--brand);font-style:normal;font-weight:800;letter-spacing:.03em} .bde-app .form-sub::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,var(--line),transparent)}
    .bde-app .report-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}
    .bde-app .report-preview{white-space:pre-wrap;background:var(--surface2);border:1px dashed var(--line);border-radius:12px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;line-height:1.6;min-height:130px;color:var(--ink2)}

    .bde-app .bde-foot{font-size:11.5px;color:var(--muted);margin-top:14px;line-height:1.6} .bde-app .bde-foot code{background:var(--surface2);padding:1px 5px;border-radius:5px;border:1px solid var(--line)}

    @media(max-width:1000px){
      .bde-app .hero,.bde-app .grid-2,.bde-app .grid-3,.bde-app .strategy{grid-template-columns:1fr} .bde-app .kpis{grid-template-columns:1fr 1fr} .bde-app .drivers{grid-template-columns:repeat(2,1fr)} .bde-app .principles{grid-template-columns:1fr} .bde-app .form-grid{grid-template-columns:repeat(2,1fr)} .bde-app .field.span4{grid-column:span 2}
    }
    @media(max-width:560px){.bde-app{padding:12px 14px 40px} .bde-app .kpis,.bde-app .mini3,.bde-app .steps3,.bde-app .form-grid{grid-template-columns:1fr} .bde-app .field.span2,.bde-app .field.span4{grid-column:span 1} .bde-app .fr{grid-template-columns:110px 1fr 42px} .bde-app .scr{grid-template-columns:130px 1fr 40px}}
    @media(prefers-reduced-motion:reduce){.bde-app *{transition:none!important}}
    </style>

    <div class="bde-app" id="bdeApp">
      <header class="bde-topbar">
        <div class="brand"><div class="mark">VA</div><div><h1>Performance Command Centre</h1><p>Strategy → daily execution → verified revenue → commission → growth</p></div></div>
        <div class="controls">
          <?php if ($bdm_is_admin): ?>
          <div class="control"><label>View as (admin)</label>
            <select id="viewAsSelect" onchange="if(this.value)window.location.href='bdm_dashboard.php?as='+this.value;">
              <?php foreach ($bdm_people as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo $p['id'] === $bdm_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']) . ' (' . htmlspecialchars($p['role']) . ')'; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="control"><label>Analytics month</label><select id="periodSelect"></select></div>
          <button class="tbtn" id="themeBtn" type="button">🌙 Dark</button>
          <div class="profile-chip"><span class="a"><?php echo htmlspecialchars($bdm['initials'] ?: 'BDM'); ?></span><div><b><?php echo htmlspecialchars($bdm['name'] ?: 'Business Development Manager'); ?></b><span>BDM · All SBUs</span></div></div>
        </div>
      </header>
      <nav class="tabs" aria-label="Dashboard sections">
        <button class="tab active" data-v="command"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Command Centre</button>
        <button class="tab" data-v="pipeline"><svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>Pipeline &amp; Conversion</button>
        <button class="tab" data-v="report"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/></svg>Daily Report</button>
        <button class="tab" data-v="strategy"><svg viewBox="0 0 24 24"><path d="M12 20v-6M6 20v-3M18 20v-10"/><circle cx="12" cy="11" r="1.6" fill="currentColor" stroke="none"/><circle cx="6" cy="14" r="1.6" fill="currentColor" stroke="none"/><circle cx="18" cy="7" r="1.6" fill="currentColor" stroke="none"/></svg>Strategy &amp; Scorecard</button>
      </nav>
      <main id="workspace"></main>
    </div>

    <script>
    (() => {
      "use strict";
      const root=document.getElementById("bdeApp");
      const B={
        name:<?php echo json_encode($bdm['name'] ?: 'Business Development Manager', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BDM"'; ?>,
        initials:<?php echo json_encode($bdm['initials'] ?: 'BDM', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BDM"'; ?>,
        title:"Business Development Manager", dept:"All SBUs", level:"Commercial leadership",
        target:<?php echo (float) $bdm['target']; ?>, actual:<?php echo (float) $bdm['actual']; ?>, forecast:<?php echo (float) $bdm['forecast']; ?>, pipeline:<?php echo (float) $bdm['pipeline']; ?>, collection:<?php echo (float) $bdm['collection']; ?>,
        personalTarget:<?php echo (float) $bdm['personalTarget']; ?>, personalActual:<?php echo (float) $bdm['personalActual']; ?>, personalPipeline:<?php echo (float) $bdm['personalPipeline']; ?>, personalClients:<?php echo (int) $bdm['personalClients']; ?>,
        intl:<?php echo json_encode($bdm['intl'] ?: null, JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null'; ?>,
        totalLeads:<?php echo (int) $bdm['totalLeads']; ?>, clients:<?php echo (int) $bdm['clients']; ?>,
        mandate:"Make growth systematic across all SBUs while remaining a direct strategic revenue producer.",
        mandateText:"The BDM controls consolidated revenue, qualified pipeline, proposals, strategic accounts, marketing-to-sales conversion, collections, CRM discipline, HOD performance and early recovery action.",
        focus:"Move blocked high-value accounts, correct weak SBUs and ensure every HOD has an evidence-based forecast and recovery action.",
        funnel:<?php echo json_encode(!empty($bdm['funnel']) ? $bdm['funnel'] : [['Leads', 0], ['Paid clients', 0]], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[["Leads",0],["Paid clients",0]]'; ?>,
        sources:<?php echo json_encode($bdm['sources'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        alerts:<?php echo json_encode($bdm['alerts'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        crossSbu:<?php echo json_encode($bdm['crossSbu'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        dailyRhythm:[
          ["8:00–8:30","Review consolidated revenue, the weakest SBU, strategic accounts, RFPs, collections and overdue actions."],
          ["8:30–9:00","Set the day's SBU recovery, strategic-account and personal-revenue outcomes."],
          ["9:00–11:00","Call and coach HODs; personally advance the highest-value blocked accounts."],
          ["11:00–1:00","Audit SBU forecasts, proposals, tenders and cross-SBU opportunities."],
          ["2:00–4:30","Strategic-account meetings, negotiations and executive coordination."],
          ["4:30–5:15","Confirm every SBU next action, update CRM and submit the consolidated report."]
        ],
        principles:[
          ["Balanced growth beats a single hero SBU","Strong results in one department must never mask serious underperformance in another."],
          ["Every forecast is evidence-based","No HOD forecast without stage, value, probability, owner and a dated next action."],
          ["Lead by intervention, not observation","Move blocked high-value deals and correct weak SBUs before month-end, not after."]
        ],
        sbus:<?php echo json_encode($bdm['sbus'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>
      };
      const periods=[{label:<?php echo json_encode(date('F Y', strtotime($bdm_to)), JSON_INVALID_UTF8_SUBSTITUTE) ?: '"This month"'; ?>,working:<?php echo (int) date('t', strtotime($bdm_to)); ?>,elapsed:<?php echo (int) max(1, min((int) date('j', strtotime($bdm_to)), (int) date('t', strtotime($bdm_to)))); ?>}];
      const state={p:0,view:"command"};

      const nf=new Intl.NumberFormat("en-KE",{maximumFractionDigits:0});
      const kMoney=v=>{const a=Math.abs(v||0);if(a>=1e6)return "KES "+(v/1e6).toFixed(2).replace(/\.00$/,"")+"M";if(a>=1e3)return "KES "+Math.round(v/1e3)+"K";return "KES "+nf.format(Math.round(v||0));};
      // SBU display is metric-aware: International is client-based, the rest are KES.
      const sbuActual=d=>d.kes?kMoney(d.actual):nf.format(Math.round(d.actual||0))+" clients";
      const sbuTarget=d=>d.kes?kMoney(d.target):nf.format(Math.round(d.target||0))+" clients";
      const liveSbus=()=>B.sbus.filter(d=>!d.placeholder);
      const pct=(v,d=1)=>(v*100).toFixed(d).replace(/\.0$/,"")+"%";
      const esc=s=>String(s??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
      const el=id=>document.getElementById(id);
      const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
      const period=()=>periods[state.p];

      function pace(){const p=period();const expected=B.target*(p.elapsed/p.working);const ratio=expected?B.actual/expected:0;const status=ratio>=1?"green":ratio>=.85?"amber":"red";return {expected,ratio,status,label:status==="green"?"On pace":status==="amber"?"At risk":"Behind pace"};}
      const scol=s=>s==="green"?"var(--jade)":s==="amber"?"var(--amber)":"var(--coral)";
      function commission(){
        const s=B;const orgAtt=s.target>0?s.actual/s.target:0;const personal=s.personalActual;
        const live=liveSbus();const nS=live.length||1;
        const personalComm=personal>=7500000?150000:personal>=6000000?120000:personal>=5000000?90000:personal>=4000000?60000:0;
        const sbus80=live.filter(d=>(+d.attn)>=.8).length;
        const need=Math.max(1,Math.ceil(nS*0.8));
        const leadership=orgAtt>=1.1?125000:orgAtt>=1?100000:orgAtt>=.9?75000:orgAtt>=.8?50000:0;
        const noneBelow50=live.every(d=>(+d.attn)>=.5);
        const gated=sbus80>=need&&s.collection>=.9&&noneBelow50;
        const current=gated?personalComm+leadership:Math.round(personalComm*.7);
        const atTarget=90000+100000;
        const gates=[
          ["Organization reaches 80%+",orgAtt>=.8,pct(orgAtt,0)],
          [`At least ${need} of ${nS} SBUs at 80%+`,sbus80>=need,sbus80+" of "+nS],
          ["No SBU below 50%",noneBelow50,live.filter(d=>(+d.attn)<.5).length+" below"],
          ["Organization collection at 90%+",s.collection>=.9,pct(s.collection,0)],
          ["Personal strategic sales (KES 4M+)",personal>=4000000,kMoney(personal)]
        ];
        const unlock=gated?"Organization leadership gate unlocked":"Complete the balanced-SBU and 90% collection gates.";
        const rule="Personal strategic-acquisition commission plus an organization-wide leadership commission, with a 30% leadership hold-back until the balanced-SBU and collection gates are satisfied.";
        return {current,atTarget,gates,unlock,rule};
      }

      /* ---------- shared blocks ---------- */
      function strategyStrip(){return `<section class="strategy"><div><div class="eyebrow">Multi-SBU commercial command</div><h2>${esc(B.mandate)}</h2><p>${esc(B.mandateText)}</p></div><div class="focus"><b>Today's strategic focus</b><span>${esc(B.focus)}</span></div></section>`;}

      function kpiBlock(){
        const att=B.target>0?B.actual/B.target:0;const c=commission();const live=liveSbus();const kesN=live.filter(d=>d.kes).length;const sbus80=live.filter(d=>(+d.attn)>=.8).length;
        const intl=B.intl;const intlCell=intl?`${nf.format(Math.round(intl.actual))} / ${nf.format(Math.round(intl.target))} clients`:"—";
        const pT=B.personalTarget>0?pct(B.personalActual/B.personalTarget)+" of "+kMoney(B.personalTarget):"no personal target";
        const items=[
          ["Organization target (KES SBUs)",kMoney(B.target),kesN+" revenue SBU"+(kesN===1?"":"s"),"flat","var(--slate)"],
          ["Cleared revenue",kMoney(B.actual),pct(att)+" attainment","up","var(--jade)"],
          ["Month-end forecast",kMoney(B.forecast),(B.target>0?pct(B.forecast/B.target):"0%")+" projected","flat","var(--slate)"],
          ["International (clients)",intlCell,intl?pct(intl.attn,0)+" of target":"not resolved","up","var(--violet)"],
          ["BDM personal sales",kMoney(B.personalActual),pT,"up","var(--slate)"],
          ["Commission estimate",kMoney(c.current),c.current>0?"Personal + leadership":"Not yet unlocked","flat","var(--amber)"]
        ];
        const dt={up:'<span class="dic">↗</span> Positive movement',down:'<span class="dic">↘</span> Below pace',flat:'<span class="dic">•</span> Live from CRM / Finance'};
        const kIcons=[
          '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg>',
          '<svg viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M12 3l9 5-9 5-9-5z"/><path d="M3 12l9 5 9-5"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M20 8H6a2 2 0 0 1 0-4h13v4M3 6v11a2 2 0 0 0 2 2h15V8"/><circle cx="16.5" cy="13.5" r="1.4" fill="currentColor" stroke="none"/></svg>',
          '<svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9M9 3h6"/></svg>'
        ];
        return `<div class="kpis">${items.map(([l,v,m,d,a],i)=>`<div class="kpi" style="--acc:${a}"><span class="kicon">${kIcons[i%kIcons.length]}</span><div class="lab">${l}</div><div class="val num">${v}</div><div class="meta">${m}</div><div class="delta ${d}">${dt[d]}</div></div>`).join("")}</div>`;
      }

      function progressCard(){
        const p=period();const att=B.actual/B.target;const ps=pace();const daysLeft=Math.max(0,p.working-p.elapsed);
        const motiv=ps.status==="green"?"<b>Keep going:</b> You're at or above required pace. Protect collections, quality and stretch opportunities.":ps.status==="amber"?"<b>Close the gap:</b> You're near pace. Focus on the opportunities nearest to payment and remove today's biggest blocker.":"<b>Recover now:</b> The current pace will miss target. Start a quantified recovery plan today — not at month end.";
        return `<div class="card prog">
          <div class="chead"><h4>Progress to target</h4><span class="chip ${ps.status==="green"?"jade":ps.status==="amber"?"amber":"coral"} num">${pct(att)}</span></div>
          <div class="pl">Cleared revenue · <b class="num">${kMoney(B.actual)} / ${kMoney(B.target)}</b></div>
          <div class="bar"><div class="bf" style="width:${clamp(att*100,0,100)}%"></div><div class="exp" style="left:${clamp((p.elapsed/p.working)*100,0,100)}%"></div></div>
          <div class="mini3"><div class="cm"><span>Expected by today</span><b class="num">${kMoney(ps.expected)}</b></div><div class="cm"><span>Remaining gap</span><b class="num">${kMoney(Math.max(0,B.target-B.actual))}</b></div><div class="cm"><span>Days left</span><b class="num">${daysLeft}</b></div></div>
          <div class="motiv ${ps.status}">${motiv}</div>
        </div>`;
      }

      function trendSVG(){
        const target=B.target,actual=B.actual,forecast=B.forecast;const p=period();const frac=p.elapsed/p.working;const N=9;
        const pts=[];for(let i=0;i<N;i++){const x=i/(N-1);pts.push(x<=frac?actual*(x/Math.max(.01,frac)):actual+(forecast-actual)*((x-frac)/Math.max(.01,1-frac)));}
        const max=Math.max(target,forecast,...pts)*1.1;const w=560,h=200,pd=30;
        const P=pts.map((v,i)=>[pd+i*(w-2*pd)/(N-1),h-pd-v/max*(h-2*pd)]);
        const line=P.map((q,i)=>(i?"L":"M")+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ");
        const area=`M${P[0][0]},${h-pd} `+P.map(q=>"L"+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ")+` L${P[N-1][0]},${h-pd} Z`;
        const ty=h-pd-target/max*(h-2*pd);const tx=pd+frac*(w-2*pd);
        return `<svg class="chart" viewBox="0 0 ${w} ${h}" role="img" aria-label="Revenue pace and forecast">
          ${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${pd}" y1="${(pd+t*(h-2*pd)).toFixed(1)}" x2="${w-pd}" y2="${(pd+t*(h-2*pd)).toFixed(1)}"/>`).join("")}
          <line class="tline" x1="${pd}" y1="${ty.toFixed(1)}" x2="${w-pd}" y2="${ty.toFixed(1)}"/><text x="${w-pd}" y="${(ty-6).toFixed(1)}" text-anchor="end">Target ${kMoney(target)}</text>
          <line x1="${tx.toFixed(1)}" y1="${pd}" x2="${tx.toFixed(1)}" y2="${h-pd}" stroke="var(--faint)" stroke-dasharray="3 3"/>
          <path class="area" d="${area}"/><path class="line" d="${line}"/>${P.map(q=>`<circle class="dot" cx="${q[0].toFixed(1)}" cy="${q[1].toFixed(1)}" r="3.5"/>`).join("")}
          <text x="${pd}" y="${h-8}">Start</text><text x="${tx.toFixed(1)}" y="${h-8}" text-anchor="middle">Today</text><text x="${w-pd}" y="${h-8}" text-anchor="end">Month end</text></svg>`;
      }

      // The same forecast chart as the consolidated one, parameterised for a single SBU's numbers.
      // kes=false renders the target/actual labels as client counts (International).
      function trendSVGFor(target,actual,forecast,kes){
        const fmt=kes?kMoney:(v=>nf.format(Math.round(v||0))+" clients");
        const p=period();const frac=p.elapsed/p.working;const N=9;
        const pts=[];for(let i=0;i<N;i++){const x=i/(N-1);pts.push(x<=frac?actual*(x/Math.max(.01,frac)):actual+(forecast-actual)*((x-frac)/Math.max(.01,1-frac)));}
        const max=Math.max(target,forecast,...pts,1)*1.12;const w=520,h=188,pd=28;
        const P=pts.map((v,i)=>[pd+i*(w-2*pd)/(N-1),h-pd-v/max*(h-2*pd)]);
        const line=P.map((q,i)=>(i?"L":"M")+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ");
        const area=`M${P[0][0]},${h-pd} `+P.map(q=>"L"+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ")+` L${P[N-1][0]},${h-pd} Z`;
        const ty=h-pd-target/max*(h-2*pd);const tx=pd+frac*(w-2*pd);
        return `<svg class="chart" viewBox="0 0 ${w} ${h}" style="height:188px" role="img" aria-label="SBU forecast">
          ${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${pd}" y1="${(pd+t*(h-2*pd)).toFixed(1)}" x2="${w-pd}" y2="${(pd+t*(h-2*pd)).toFixed(1)}"/>`).join("")}
          <line class="tline" x1="${pd}" y1="${ty.toFixed(1)}" x2="${w-pd}" y2="${ty.toFixed(1)}"/><text x="${w-pd}" y="${(ty-6).toFixed(1)}" text-anchor="end">Target ${fmt(target)}</text>
          <line x1="${tx.toFixed(1)}" y1="${pd}" x2="${tx.toFixed(1)}" y2="${h-pd}" stroke="var(--faint)" stroke-dasharray="3 3"/>
          <path class="area" d="${area}"/><path class="line" d="${line}"/>${P.map(q=>`<circle class="dot" cx="${q[0].toFixed(1)}" cy="${q[1].toFixed(1)}" r="3"/>`).join("")}
          <text x="${pd}" y="${h-8}">Start</text><text x="${tx.toFixed(1)}" y="${h-8}" text-anchor="middle">Today</text><text x="${w-pd}" y="${h-8}" text-anchor="end">Month end</text></svg>`;
      }
      // One forecast card per SBU (same look as the consolidated chart), laid out two-up.
      function sbuForecasts(){
        const live=liveSbus();
        if(!live.length) return `<p style="color:var(--muted);font-size:12.5px">No SBUs to chart yet.</p>`;
        const cards=live.map(d=>{const fore=d.forecast||0;const att=(+d.attn)||0;const chip=att>=1?"jade":att>=.7?"amber":"coral";const foreLab=d.kes?kMoney(fore):nf.format(Math.round(fore))+" clients";return `<div class="card"><div class="chead"><div><h4>${esc(d.name)}</h4><p style="font-size:11px;color:var(--muted);margin:2px 0 0">${esc(d.leader||"")}</p></div><span class="chip ${chip}">${foreLab} forecast</span></div>${trendSVGFor(d.target,d.actual,fore,d.kes)}<div style="font-size:11.5px;color:var(--muted);margin-top:8px">${sbuActual(d)} of ${sbuTarget(d)} · ${pct(att,0)} to target</div></div>`;}).join("");
        return `<section class="grid-2">${cards}</section>`;
      }

      function actionsCard(){
        const list=[
          ["red","Recover the weakest SBU","Require a quantified seven-day recovery forecast and a named opportunity list.","Today"],
          ["amber","Unblock strategic accounts","Use executive access, pricing, partnerships or internal coordination to move high-value deals.","Today"],
          ["blue","Audit HOD forecasts","Every SBU forecast must be supported by stage, value, probability, owner and next action.","Before weekly review"],
          ["green","Protect balanced performance","Strong results in one SBU must not hide serious underperformance elsewhere.","Ongoing"]
        ];
        return `<div class="card"><div class="chead"><h4>Today's action centre</h4><span class="chip coral">Action required</span></div><div class="list">${list.map(([c,b,p,d])=>`<div class="arow"><span class="pd ${c}"></span><div><b>${esc(b)}</b><p>${esc(p)}</p></div><span class="due">${esc(d)}</span></div>`).join("")}</div></div>`;
      }
      function teamTable(){
        const avatarCols=["var(--slate)","#2f8f88","var(--brand)","var(--violet)","var(--gold)"];
        const p=period();
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>SBU</th><th>Target</th><th>Cleared</th><th>Attainment</th><th>Collection</th><th>Status / response</th></tr></thead><tbody>${B.sbus.map((d,i)=>{const ini=d.name.split(/\s+/).map(x=>x[0]).slice(0,2).join("");if(d.placeholder){return `<tr style="opacity:.6"><td><div class="prow"><span class="a" style="background:var(--faint)">${ini}</span><div><b>${esc(d.name)}</b><span>${esc(d.leader)}</span></div></div></td><td class="num" colspan="4" style="color:var(--muted)">Not yet configured in the CRM</td><td><span class="chip slate">Placeholder</span></td></tr>`;}const a=(+d.attn)||0;const exp=d.target*(p.elapsed/p.working);const st=d.actual>=exp?"green":d.actual>=exp*.85?"amber":"red";const lbl=st==="green"?"On pace":st==="amber"?"At risk":"Behind pace";const resp=st==="red"?"Recovery plan + daily monitoring":st==="amber"?"Corrective action within 24h":"Protect quality; pursue stretch";const nameCell=d.bdoId?`<a href="bdo_dashboard.php?as=${d.bdoId}" title="Open ${esc(d.name)} dashboard" style="color:inherit;text-decoration:none;border-bottom:1px dotted var(--faint)">${esc(d.name)}</a>`:esc(d.name);return `<tr><td><div class="prow"><span class="a" style="background:${avatarCols[i%avatarCols.length]}">${ini}</span><div><b>${nameCell}</b><span>${esc(d.leader)}${d.kes?"":" · clients"}</span></div></div></td><td class="num">${sbuTarget(d)}</td><td class="num">${sbuActual(d)}</td><td><span class="mini-track"><div style="width:${clamp(a*100,0,100)}%;background:${scol(st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(a,0)}</b></td><td class="num">${pct(d.collection,0)}</td><td><span class="sbadge s${st[0]}"><span class="dot"></span>${lbl}</span><div style="font-size:10.5px;color:var(--muted);margin-top:5px">${resp}</div></td></tr>`;}).join("")}</tbody></table></div></div>`;
      }

      /* ---------- executive master view (BDM request) ---------- */
      function execRevenueBreakdown(){
        const shortName={"International":"Int'l","Virtual":"Virtual","Corporate":"Corporate","Digital Solutions":"Digital","Academic":"Academic"};
        // Every SBU, normalised to its OWN target so client-based International compares to the KES ones.
        const data=liveSbus().map(d=>({name:shortName[d.name]||d.name,attn:(+d.attn)||0,tLab:sbuTarget(d),aLab:sbuActual(d)}));
        const maxA=Math.max(1.1,...data.map(d=>d.attn))*1.12;
        const w=680,h=286,pd=42,base=h-pd-26,plot=base-pd,step=(w-2*pd)/Math.max(1,data.length),bw=38,gap=14;
        const bars=data.map((d,i)=>{const cx=pd+step*i+step/2;const xT=cx-bw-gap/2,xA=cx+gap/2;const th=1/maxA*plot;const ah=Math.max(3,d.attn/maxA*plot);const st=d.attn>=1?"green":d.attn>=.7?"amber":"red";return `<g>
          <rect x="${xT.toFixed(1)}" y="${(base-th).toFixed(1)}" width="${bw}" height="${th.toFixed(1)}" rx="4" fill="var(--slate)"/>
          <rect x="${xA.toFixed(1)}" y="${(base-ah).toFixed(1)}" width="${bw}" height="${ah.toFixed(1)}" rx="4" fill="${scol(st)}"/>
          <text x="${(xT+bw/2).toFixed(1)}" y="${(base-th-7).toFixed(1)}" text-anchor="middle" style="font-size:10px;font-weight:700;fill:var(--slate)">${d.tLab}</text>
          <text x="${(xA+bw/2).toFixed(1)}" y="${(base-ah-7).toFixed(1)}" text-anchor="middle" style="font-size:10px;font-weight:800;fill:${scol(st)}">${d.aLab}</text>
          <text x="${cx.toFixed(1)}" y="${(base+16).toFixed(1)}" text-anchor="middle" style="font-weight:700">${esc(d.name)}</text>
          <text x="${cx.toFixed(1)}" y="${(base+30).toFixed(1)}" text-anchor="middle" style="font-size:10px;fill:var(--muted)">${pct(d.attn,0)} of target</text></g>`;}).join("");
        return `<div class="card"><div class="chead"><div><h4>Cleared vs target — by SBU</h4><p>How far each SBU has cleared toward its own target. International is in clients, the rest in KES.</p></div></div>
          <svg class="chart" viewBox="0 0 ${w} ${h}" style="height:286px" role="img" aria-label="Cleared versus target by SBU">${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${pd}" y1="${(pd+t*plot).toFixed(1)}" x2="${w-pd}" y2="${(pd+t*plot).toFixed(1)}"/>`).join("")}${bars}</svg>
          <div class="legend"><span class="lg"><i style="background:var(--slate)"></i>Target</span><span class="lg"><i style="background:var(--jade)"></i>Cleared &nbsp;<span style="color:var(--muted)">· colour = pace</span></span></div></div>`;
      }
      function execTargetProgress(){
        const p=period();
        const rows=liveSbus().map(d=>{const a=(+d.attn)||0;const exp=d.target*(p.elapsed/p.working);const st=d.actual>=exp?"green":d.actual>=exp*.85?"amber":"red";return `<div class="tvp"><div class="tvp-top"><b>${esc(d.name)}</b><span class="chip ${st==="green"?"jade":st==="amber"?"amber":"coral"}">${pct(a,0)}</span></div><div class="track2"><div class="fill2" style="width:${clamp(a*100,0,100)}%;background:${scol(st)}"></div></div><div class="tvp-sub">${sbuActual(d)} / ${sbuTarget(d)}</div></div>`;}).join("");
        return `<div class="card"><div class="chead"><div><h4>Target vs actual — by department</h4><p>Closed revenue against each departmental quota.</p></div><span class="chip slate">Colour = pace</span></div><div class="tvp-grid">${rows}</div></div>`;
      }
      function execTopDeals(){
        // Real cross-SBU opportunities flagged on BDO/BDE field visits (bde_visits.opportunity_note).
        const rows=(B.crossSbu||[]);
        if(!rows.length) return `<div class="card"><div class="chead"><h4>Cross-SBU opportunities</h4><span class="chip slate">From field visits</span></div><p style="color:var(--muted);font-size:12.5px;margin:0;line-height:1.6">No opportunities flagged from field visits yet. As BDOs and BDEs log field visits with opportunity notes, the highest-value ones surface here for cross-SBU action. A structured strategic-deals pipeline (value · stage · owner) needs a deals table — not yet in the CRM.</p></div>`;
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>#</th><th>Opportunity flagged on a field visit</th></tr></thead><tbody>${rows.map((x,i)=>`<tr><td class="num">${i+1}</td><td>${esc(x)}</td></tr>`).join("")}</tbody></table></div></div>`;
      }

      /* ---------- views ---------- */
      function vCommand(){
        const ps=pace();
        return `${strategyStrip()}
          <section class="hero">
            <div class="card"><div class="chead"><h4>Organization portfolio</h4><span class="pace-pill ${ps.status==="green"?"pg":ps.status==="amber"?"pa":"pr"}"><span class="dot"></span>${ps.label} · pace ${pct(ps.ratio,0)}</span></div>${kpiBlock()}</div>
            ${progressCard()}
          </section>

          <div class="section-tag"><h3>SBU performance comparison</h3><span>The whole picture — each SBU drills into its department (BDO) dashboard</span><div class="rule"></div></div>
          ${teamTable()}
          ${execRevenueBreakdown()}

          <div class="section-tag"><h3>Cross-SBU opportunities</h3><span>Opportunities flagged across the SBUs' field visits</span><div class="rule"></div></div>
          ${execTopDeals()}

          <div class="section-tag"><h3>Forecast by SBU</h3><span>Each unit's own pace to month-end, against its own target</span><div class="rule"></div></div>
          ${sbuForecasts()}

          <div class="section-tag"><h3>Organization forecast &amp; today's execution</h3><span>Consolidated KES trajectory and the actions in play now</span><div class="rule"></div></div>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Consolidated revenue forecast</h4><span class="chip jade">${kMoney(B.forecast)} forecast</span></div>${trendSVG()}<div style="font-size:11.5px;color:var(--muted);margin-top:10px">KES SBUs combined. The forecast moves whenever stage, probability, payment date or cleared revenue changes.</div></div>
            ${actionsCard()}
          </section>`;
      }

      function vPipeline(){
        const fmax=Math.max(1,B.funnel[0][1]);const smax=Math.max(1,...B.sources.map(s=>s[1]));
        const alerts=B.alerts||[];
        const sHTML=B.sources.length?B.sources.map(([n,v])=>`<div class="src"><label>${esc(n)}</label><div class="sb"><div style="width:${v/smax*100}%"></div></div><b>${nf.format(v)}</b></div>`).join(""):`<p style="color:var(--muted);font-size:12.5px;margin:0">No lead-source data across the SBUs this period.</p>`;
        const aHTML=alerts.length?alerts.map(a=>`<div class="arow"><span class="pd red"></span><div><b>${esc(a.text||((a.n||0)+" unread WhatsApp chats to reply"))}</b><p>${esc(a.name||"")}${a.sbu?" · "+esc(a.sbu):""}</p></div><span class="due">${nf.format(a.n||0)}</span></div>`).join(""):`<p style="color:var(--muted);font-size:12.5px;margin:0">No unread WhatsApp chats awaiting reply across the SBUs. </p>`;
        return `
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Acquisition &amp; conversion funnel</h4><span class="chip slate">Live · all SBUs</span></div><div class="funnel">${B.funnel.map(([l,n],i)=>`<div class="fr"><label>${esc(l)}</label><div class="fbar"><div style="width:${Math.max(9,n/fmax*100)}%">${nf.format(n)}</div></div><span class="cv">${i?Math.round(n/Math.max(1,B.funnel[i-1][1])*100)+"%":"100%"}</span></div>`).join("")}</div><div style="font-size:11px;color:var(--muted);margin-top:8px">Consolidated leads → paid clients across every SBU.</div></div>
            <div class="card"><div class="chead"><h4>Lead-source contribution</h4><span class="chip slate">Leads by source</span></div>${sHTML}</div>
          </section>
          <div class="card"><div class="chead"><h4>Unread WhatsApp chats awaiting reply</h4><span class="chip ${alerts.length?"coral":"jade"}">${alerts.length} ${alerts.length===1?"person":"people"}</span></div><div class="list">${aHTML}</div><div style="font-size:11px;color:var(--muted);margin-top:8px">Open WhatsApp conversations assigned to each person with an unanswered message — a response backlog, not sales enquiries.</div></div>`;
      }

      function vReport(){
        const p=period();
        const live=liveSbus();
        const sbusGreen=live.filter(d=>d.actual>=d.target*(p.elapsed/p.working)).length;
        // [label, type, value, _, placeholder] — guidance goes in the placeholder (grey), not the value.
        const fields=[
          ["Organization daily revenue target (KES SBUs)","number",Math.round(B.target/p.working)],
          ["Actual cleared revenue today","number",Math.round(B.actual/p.elapsed)],
          ["SBUs at pace (of "+live.length+")","number",sbusGreen],
          ["International clients MTD","number",B.intl?Math.round(B.intl.actual):0],
          ["BDM personal revenue MTD","number",Math.round(B.personalActual)],
          ["Consolidated qualified pipeline","number",Math.round(B.pipeline)],
          ["SBU performance summary","textarea",live.map(d=>`${d.name}: ${sbuActual(d)} / ${sbuTarget(d)}; forecast ${d.kes?kMoney(d.forecast):Math.round(d.forecast)+" clients"}`).join("\n"),false,""],
          ["Strategic accounts and blocked deals","textarea","",false,"Account, value, stage, owner, blocker, executive action and next date."],
          ["HOD coaching / recovery decisions","textarea","",false,"Named HOD, issue, action, deadline and review point."],
          ["CEO decisions required","textarea","",false,"Budget, pricing, executive access, technology, legal, payment or capacity decision."]
        ];
        const fieldHTML=f=>f[1]==="textarea"
          ?`<div class="field span2"><label>${esc(f[0])}</label><textarea data-label="${esc(f[0])}" placeholder="${esc(f[4]||"")}">${esc(f[2])}</textarea></div>`
          :`<div class="field"><label>${esc(f[0])}</label><input data-label="${esc(f[0])}" type="text" inputmode="numeric" value="${f[2]===""?"":nf.format(f[2])}"></div>`;
        const nums=fields.filter(f=>f[1]==="number").map(fieldHTML).join("");
        const texts=fields.filter(f=>f[1]==="textarea").map(fieldHTML).join("");
        const dlSvg='<svg viewBox="0 0 24 24" style="width:14px;height:14px;vertical-align:-2px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M12 3v12M8 11l4 4 4-4M5 21h14"/></svg>';
        return `
          <div class="card"><div class="chead"><h4>BDM consolidated commercial report</h4><span class="chip jade">Auto-prefilled</span></div>
            <div id="reportForm">
              <div class="form-sub">Today's numbers <i>· auto-prefilled</i></div>
              <div class="form-grid">${nums}</div>
              <div class="form-sub" style="margin-top:18px">Your narrative <i>· the human judgement</i></div>
              <div class="form-grid">${texts}</div>
            </div>
            <div class="report-actions"><button class="tbtn solid" id="genReport" type="button">Generate report summary</button><button class="tbtn" id="dlReport" type="button">${dlSvg} Download</button></div>
          </div>
          <div class="card"><div class="chead"><h4>Generated management summary</h4><span class="chip jade">Evidence-linked</span></div><div id="reportPreview" class="report-preview">Fill in the fields above, then hit <b>Generate report summary</b> to compile your numbers and narrative into a shareable report. Use <b>Download</b> to save it.</div></div>
          <section class="grid-3">
            ${[["Automatic evidence","Revenue, payments, activity logs, opportunities, meetings, proposals and CRM completeness are system-calculated."],["Required human judgement","You explain why performance moved, what is blocked, what was learned and which support or decision is required."],["Manager workflow","Your supervisor reviews, comments, approves or returns the report and converts commitments into tracked actions."]].map(([a,b])=>`<div class="card"><h4>${esc(a)}</h4><p style="font-size:12.5px;color:var(--muted);margin:8px 0 0;line-height:1.5">${esc(b)}</p></div>`).join("")}
          </section>`;
      }

      function vStrategy(){
        return `
          <div class="card"><div class="chead"><h4>Role mandate</h4><span class="chip jade">Commercial command</span></div><div class="motiv green"><b>${esc(B.mandate)}</b><br>${esc(B.mandateText)}</div></div>
          <div class="card"><div class="chead"><h4>Non-negotiable operating principles</h4></div><div class="principles">${B.principles.map(([a,b])=>`<div class="principle"><b>${esc(a)}</b><p>${esc(b)}</p></div>`).join("")}</div></div>
          <div class="card"><div class="chead"><h4>Daily operating rhythm</h4></div><div class="timeline">${B.dailyRhythm.map(([t,x])=>`<div class="time-row"><time>${esc(t)}</time><div>${esc(x)}</div></div>`).join("")}</div></div>
          <section class="grid-3">
            <div class="card"><div class="chead"><h4>Green response</h4><span class="chip jade">At / above pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Protect quality, collections and client experience; pursue stretch opportunities and share winning practices.</p></div>
            <div class="card"><div class="chead"><h4>Amber response</h4><span class="chip amber">Near pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Agree corrective action within 24 hours, intensify senior support and concentrate on the nearest commercial next steps.</p></div>
            <div class="card"><div class="chead"><h4>Red response</h4><span class="chip coral">Below pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Create a quantified recovery plan, monitor daily and escalate decisions or resources before the gap becomes irreversible.</p></div>
          </section>`;
      }

      function render(){
        const v=state.view;
        el("workspace").innerHTML=v==="command"?vCommand():v==="pipeline"?vPipeline():v==="report"?vReport():vStrategy();
        if(v==="report")bindReport();
      }
      function bindReport(){
        var g=el("genReport"); if(g) g.addEventListener("click",genReport);
        var d=el("dlReport"); if(d) d.addEventListener("click",downloadReport);
      }
      function downloadReport(){
        if(!window.__reportBlob)genReport();
        const a=document.createElement("a");a.href=URL.createObjectURL(window.__reportBlob);a.download="Vantage_BDM_"+(period().label||"report").replace(/\s+/g,"_")+"_Consolidated_Report.html";a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);
        window.__reportBlob=null;
      }
      function buildReportHTML(){
        const today=new Date().toLocaleDateString("en-GB",{weekday:"long",day:"2-digit",month:"long",year:"numeric"});
        const att=B.target>0?B.actual/B.target:0;const attW=Math.min(100,Math.max(0,Math.round(att*100)));
        const numRows=[...root.querySelectorAll("#reportForm input")].map(x=>`<tr><td class="k">${esc(x.dataset.label)}</td><td class="v">${esc(x.value.trim()||"—")}</td></tr>`).join("");
        const narr=[...root.querySelectorAll("#reportForm textarea")].map(x=>`<div class="nblock"><div class="nlabel">${esc(x.dataset.label)}</div><div class="ntext">${esc(x.value.trim()||"—")}</div></div>`).join("");
        const live=liveSbus();const on=live.filter(d=>(+d.attn)>=(period().elapsed/period().working)).length;const intl=B.intl;
        const dash=[["Remaining to target",kMoney(Math.max(0,B.target-B.actual))],["Month-end forecast",kMoney(B.forecast)],["Collection rate",pct(B.collection,0)],["SBUs on pace",on+" / "+live.length],["International clients",intl?(nf.format(Math.round(intl.actual))+" / "+nf.format(Math.round(intl.target))):"—"],["BDM personal sales",kMoney(B.personalActual)],["Commission estimate",kMoney(commission().current)]]
          .map(r=>`<tr><td class="k">${r[0]}</td><td class="v">${r[1]}</td></tr>`).join("");
        return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BDM Consolidated Report — ${esc(B.name)}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#2a2018;background:#eeeeec;padding:30px;-webkit-font-smoothing:antialiased}
.wrap{max-width:820px;margin:0 auto;background:#fffdfa;border-radius:16px;overflow:hidden;box-shadow:0 20px 54px rgba(45,28,12,.17);border:1px solid #eee4d4}
.rhead{background:linear-gradient(125deg,#4a2c18,#291409);color:#fff;padding:32px 44px;display:flex;justify-content:space-between;align-items:center;gap:20px}
.rhead h1{font-family:Georgia,"Times New Roman",serif;font-size:29px;font-weight:700;letter-spacing:-.01em;line-height:1.06}
.rhead p{opacity:.68;font-size:11px;margin-top:9px;letter-spacing:.16em;text-transform:uppercase}
.rlogo{flex:none;background:#fff;border-radius:12px;padding:10px 13px;display:flex;align-items:center}
.rlogo img{height:44px;width:auto;display:block}
.metastrip{display:flex;flex-wrap:wrap;gap:20px 52px;padding:22px 44px;background:#faf3e8;border-bottom:1px solid #eee4d4}
.mk{display:block;font-size:9.5px;text-transform:uppercase;letter-spacing:.13em;color:#9a9488;font-weight:700;margin-bottom:5px}
.metastrip .mv{font-size:15px;font-weight:600;color:#2a2018}
.hero{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;padding:28px 44px 20px}
.hbig{font-family:Georgia,"Times New Roman",serif;font-size:37px;font-weight:700;color:#2a2018;line-height:1;font-variant-numeric:tabular-nums}
.hsub{display:block;font-size:12.5px;color:#7a6c5c;margin-top:9px}
.hpct{font-family:Georgia,"Times New Roman",serif;font-size:35px;font-weight:700;color:#bd8a30;line-height:1;font-variant-numeric:tabular-nums}
.pbar{height:6px;border-radius:20px;background:#efe6d6;margin:0 44px 28px;overflow:hidden}
.pbar>i{display:block;height:100%;border-radius:20px;background:linear-gradient(90deg,#c99a3c,#a86f22)}
.sec{display:flex;align-items:center;gap:12px;padding:28px 44px 13px}
.sec .dot{flex:none;width:7px;height:7px;background:#bd8a30;border-radius:2px;transform:rotate(45deg)}
.sec .lbl{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.15em;color:#3d2416;white-space:nowrap}
.sec .ln{flex:1;height:1px;background:#ebe6dd}
table.nums{width:100%;border-collapse:collapse}
table.nums td{padding:11px 44px;border-bottom:1px solid #f3ebdc;font-size:13.5px}
table.nums tr:last-child td{border-bottom:none}
table.nums td.k{color:#7a6c5c}
table.nums td.v{text-align:right;font-weight:700;font-variant-numeric:tabular-nums;color:#2a2018}
.narrwrap{margin:2px 44px 4px;background:#faf6ec;border:1px solid #efe6d3;border-radius:12px}
.nblock{padding:15px 22px}
.nblock+.nblock{border-top:1px solid #f0e7d5}
.nlabel{font-size:9.5px;text-transform:uppercase;letter-spacing:.13em;color:#9a9488;font-weight:800;margin-bottom:6px}
.ntext{font-size:13.5px;color:#2a2018;line-height:1.6;white-space:pre-wrap}
footer{padding:18px 44px;font-size:10.5px;color:#a2907b;background:#faf3e8;border-top:1px solid #eee4d4;letter-spacing:.02em}
@media print{body{background:#fff;padding:0}.wrap{box-shadow:none;border-radius:0;max-width:none;border:none}}
</style></head><body>
<div class="wrap">
  <div class="rhead"><div><h1>Consolidated Commercial Report</h1><p>Vantage Africa School of Leadership</p></div><div class="rlogo"><img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" alt="Vantage Africa School of Leadership"></div></div>
  <div class="metastrip"><div><span class="mk">Business Development Manager</span><span class="mv">${esc(B.name)}</span></div><div><span class="mk">Scope</span><span class="mv">All SBUs</span></div><div><span class="mk">Date</span><span class="mv">${today}</span></div></div>
  <div class="hero"><div><span class="mk">Cleared so far (KES SBUs)</span><div class="hbig">${kMoney(B.actual)}</div><span class="hsub">of ${kMoney(B.target)} organization target</span></div><div style="text-align:right"><span class="mk">Attainment</span><div class="hpct">${pct(att)}</div></div></div>
  <div class="pbar"><i style="width:${attW}%"></i></div>
  <div class="sec"><span class="dot"></span><span class="lbl">Today's numbers</span><span class="ln"></span></div><table class="nums">${numRows}</table>
  <div class="sec"><span class="dot"></span><span class="lbl">Narrative</span><span class="ln"></span></div><div class="narrwrap">${narr}</div>
  <div class="sec"><span class="dot"></span><span class="lbl">Organization position</span><span class="ln"></span></div><table class="nums">${dash}</table>
  <footer>All figures are subject to CRM evidence and Finance verification. &middot; Generated ${today}.</footer>
</div></body></html>`;
      }
      function genReport(){
        const html=buildReportHTML();
        window.__reportBlob=new Blob([html],{type:"text/html;charset=utf-8"});
        const pv=el("reportPreview");
        pv.innerHTML=`<iframe title="Report preview" style="width:100%;height:600px;border:1px solid #dce4eb;border-radius:10px;background:#fff" src="${URL.createObjectURL(window.__reportBlob)}"></iframe><div class="report-actions" style="margin-top:14px;justify-content:flex-end"><button class="tbtn solid" id="dlReportBottom" type="button"><svg viewBox="0 0 24 24" style="width:14px;height:14px;vertical-align:-2px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M12 3v12M8 11l4 4 4-4M5 21h14"/></svg> Download report</button></div>`;
        var db=el("dlReportBottom"); if(db) db.addEventListener("click",downloadReport);
      }

      el("periodSelect").innerHTML=periods.map((p,i)=>`<option value="${i}" ${i===state.p?"selected":""}>${p.label}</option>`).join("");
      el("periodSelect").addEventListener("change",e=>{state.p=+e.target.value;render();});
      root.querySelectorAll(".tab[data-v]").forEach(a=>a.addEventListener("click",()=>{root.querySelectorAll(".tab").forEach(x=>x.classList.remove("active"));a.classList.add("active");state.view=a.dataset.v;render();}));
      el("themeBtn").addEventListener("click",()=>{const dark=root.classList.toggle("theme-dark");el("themeBtn").textContent=dark?"☀ Light":"🌙 Dark";});

      render();
    })();
    </script>
  </div>
</section>

<?php require_once 'footer.php'; ?>
