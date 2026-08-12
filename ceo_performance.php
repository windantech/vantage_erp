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
        <div class="brand"><div class="mark">VA</div><div><h1>CEO Performance Overview</h1><p>Whole organization → departments → every person, on one screen</p></div></div>
        <div class="controls">
          <div class="control"><label>View</label><select id="scopeSelect"></select></div>
          <div class="control"><label>Analytics month</label><select id="periodSelect"></select></div>
          <button class="tbtn" id="themeBtn" type="button">🌙 Dark</button>
          <div class="profile-chip"><span class="a">VA</span><div><b>Office of the CEO</b><span>Chief Executive · Whole organization</span></div></div>
        </div>
      </header>
      <nav class="tabs" aria-label="Dashboard sections" id="tabNav">
        <button class="tab active" data-v="command"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Organization</button>
        <button class="tab" data-v="people"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 5.4a3.4 3.4 0 0 1 0 5.2M20.5 20a5.5 5.5 0 0 0-3.6-5.2"/></svg>Departments &amp; People</button>
        <button class="tab" data-v="pipeline"><svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>Pipeline &amp; Conversion</button>
      </nav>
      <main id="workspace"></main>
    </div>

    <script>
    (() => {
      "use strict";
      const root=document.getElementById("bdeApp");
      const B={
        name:"Office of the CEO", initials:"VA", title:"Chief Executive Officer", dept:"Whole organization", level:"Executive",
        bdmName:"Michael Obworo Mongere", bdmInitials:"MO",
        target:52754875, actual:32070000, forecast:54860000, pipeline:117300000, collection:.90,
        personalTarget:5000000, personalActual:3720000, personalPipeline:17400000,
        mandate:"Make growth systematic across all five SBUs while remaining a direct strategic revenue producer.",
        mandateText:"The BDM controls consolidated revenue, qualified pipeline, proposals, strategic accounts, marketing-to-sales conversion, collections, CRM discipline, HOD performance and early recovery action.",
        focus:"Move blocked high-value accounts, correct weak SBUs and ensure every HOD has an evidence-based forecast and recovery action.",
        drivers:[["Active SBUs",5,"Business units"],["Strategic accounts",34,"In play"],["Proposals / tenders",28,"Live"],["HOD forecasts",5,"Audited"],["Org collection","90%","Cleared"]],
        funnel:[["Enterprise leads",13320],["Qualified",6096],["Meetings / sessions / demos",3706],["Proposal / commitment",1866],["Payment / activation",1174]],
        sources:[["Organizations / sponsorships",34],["Digital campaigns",22],["Cross-SBU referrals",17],["Alumni / partners",15],["RFPs / procurement",12]],
        priorities:[
          ["High-value organization A","Negotiation","KES 5.8M","Executive decision call","Today"],
          ["Government / donor programme","Proposal / RFP","KES 8.4M","Compliance review","Tomorrow"],
          ["Multi-participant sponsorship","Approval","KES 3.2M","Confirm nominee list","Today"],
          ["Recurring digital account","Renewal","KES 1.1M","Resolve adoption issue","Friday"]
        ],
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
        sbus:[
          {name:"International", leader:"Erick Kwemoi Ndiema", target:24000000, actual:13650000, forecast:25100000, pipeline:45200000, collection:.91, reps:[
            {name:"Brenda Achieng", title:"BDE — International", target:9000000, actual:5400000, pipeline:18800000, collection:.92, units:38},
            {name:"Samuel Kiptoo", title:"BDE — International", target:8000000, actual:4100000, pipeline:15200000, collection:.88, units:29},
            {name:"Diana Mwangi", title:"BDE — International", target:7000000, actual:4150000, pipeline:11200000, collection:.93, units:31}
          ]},
          {name:"Virtual", leader:"Francisca Ing'aa", target:11504875, actual:7920000, forecast:11850000, pipeline:21100000, collection:.87, reps:[
            {name:"Peter Kamau", title:"BDE — Virtual", target:6000000, actual:4300000, pipeline:11600000, collection:.86, units:210},
            {name:"Mercy Wanjala", title:"BDE — Virtual", target:5504875, actual:3620000, pipeline:9500000, collection:.89, units:184}
          ]},
          {name:"Corporate", leader:"Edwin Otieno", target:10000000, actual:6250000, forecast:10400000, pipeline:31800000, collection:.90, reps:[
            {name:"Grace Njoroge", title:"BDE — Corporate", target:5500000, actual:3600000, pipeline:18400000, collection:.90, units:12},
            {name:"Alein Kagunza", title:"BDE — Corporate", target:4500000, actual:2650000, pipeline:13400000, collection:.90, units:9}
          ]},
          {name:"Digital Solutions", leader:"Alein Kawinzi Kagunza", target:4850000, actual:2790000, forecast:4990000, pipeline:14600000, collection:.93, reps:[
            {name:"Austin Abere", title:"BDE — Eval360", target:2150000, actual:1370000, pipeline:8900000, collection:.95, units:84},
            {name:"Ruth Ngari", title:"BDE — 360 Appraisal", target:1200000, actual:1020000, pipeline:5700000, collection:.92, units:510}
          ]},
          {name:"Academic", leader:"Hellen Letting", target:2400000, actual:1460000, forecast:2520000, pipeline:4600000, collection:.96, reps:[
            {name:"Joseph Mutua", title:"BDE — Academic", target:1300000, actual:820000, pipeline:2600000, collection:.96, units:64},
            {name:"Lydia Chumo", title:"BDE — Academic", target:1100000, actual:640000, pipeline:2000000, collection:.95, units:52}
          ]}
        ]
      };
      const periods=[{label:"July 2026",working:23,elapsed:23},{label:"August 2026",working:21,elapsed:21},{label:"September 2026",working:22,elapsed:13},{label:"October 2026",working:23,elapsed:6}];
      const state={p:2,view:"command",scope:"org"};

      const nf=new Intl.NumberFormat("en-KE",{maximumFractionDigits:0});
      const kMoney=v=>{const a=Math.abs(v||0);if(a>=1e6)return "KES "+(v/1e6).toFixed(2).replace(/\.00$/,"")+"M";if(a>=1e3)return "KES "+Math.round(v/1e3)+"K";return "KES "+nf.format(Math.round(v||0));};
      const pct=(v,d=1)=>(v*100).toFixed(d).replace(/\.0$/,"")+"%";
      const esc=s=>String(s??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
      const el=id=>document.getElementById(id);
      const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
      const period=()=>periods[state.p];

      function pace(){const p=period();const expected=B.target*(p.elapsed/p.working);const ratio=expected?B.actual/expected:0;const status=ratio>=1?"green":ratio>=.85?"amber":"red";return {expected,ratio,status,label:status==="green"?"On pace":status==="amber"?"At risk":"Behind pace"};}
      const scol=s=>s==="green"?"var(--jade)":s==="amber"?"var(--amber)":"var(--coral)";
      function commission(){
        const s=B;const orgAtt=s.actual/s.target;const personal=s.personalActual;
        const personalComm=personal>=7500000?150000:personal>=6000000?120000:personal>=5000000?90000:personal>=4000000?60000:0;
        const sbus80=s.sbus.filter(d=>d.actual/d.target>=.8).length;
        const leadership=orgAtt>=1.1?125000:orgAtt>=1?100000:orgAtt>=.9?75000:orgAtt>=.8?50000:0;
        const gated=sbus80>=4&&s.collection>=.9&&s.sbus.every(d=>d.actual/d.target>=.5);
        const current=gated?personalComm+leadership:Math.round(personalComm*.7);
        const atTarget=90000+100000;
        const gates=[
          ["Organization reaches 80%+",orgAtt>=.8,pct(orgAtt,0)],
          ["At least 4 of 5 SBUs at 80%+",sbus80>=4,sbus80+" of 5"],
          ["No SBU below 50%",s.sbus.every(d=>d.actual/d.target>=.5),s.sbus.filter(d=>d.actual/d.target<.5).length+" below"],
          ["Organization collection at 90%+",s.collection>=.9,pct(s.collection,0)],
          ["Personal strategic sales (KES 4M+)",personal>=4000000,kMoney(personal)]
        ];
        const unlock=gated?"Organization leadership gate unlocked":"Complete the balanced-SBU and 90% collection gates.";
        const rule="Personal strategic-acquisition commission plus an organization-wide leadership commission, with a 30% leadership hold-back until the balanced-SBU and collection gates are satisfied.";
        return {current,atTarget,gates,unlock,rule};
      }

      /* ---------- shared blocks ---------- */
      function strategyStrip(){return `<section class="strategy"><div><div class="eyebrow">Enterprise intervention dashboard</div><h2>See the entire organization clearly and intervene where leadership, resources or decisions will change the result.</h2><p>The CEO dashboard consolidates revenue, forecasts, staff performance, strategic accounts, commissions, collections, product readiness and the few decisions requiring executive attention.</p></div><div class="focus"><b>Today's strategic focus</b><span>Protect organization-wide revenue, resolve the biggest bottleneck and support the opportunities with the greatest strategic value.</span></div></section>`;}

      function kpiBlock(){
        const att=B.actual/B.target;
        const commLiability=B.sbus.reduce((s,d)=>s+d.actual*0.04,0);
        const items=[
          ["Organization target",kMoney(B.target),"Approved five-SBU plan","flat","var(--slate)"],
          ["Cleared revenue",kMoney(B.actual),pct(att)+" attainment","up","var(--jade)"],
          ["Month-end forecast",kMoney(B.forecast),pct(B.forecast/B.target)+" projected","flat","var(--slate)"],
          ["Qualified pipeline",kMoney(B.pipeline),(B.pipeline/B.target).toFixed(1)+"× target","up","var(--slate)"],
          ["Collection rate",pct(B.collection,0),"Finance-cleared receipts","flat","var(--brand)"],
          ["Commission exposure",kMoney(commLiability),"Current estimated exposure","flat","var(--amber)"]
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

      function actionsCard(){
        const list=[
          ["red","Recover the weakest SBU","Require a quantified seven-day recovery forecast and a named opportunity list.","Today"],
          ["amber","Unblock strategic accounts","Use executive access, pricing, partnerships or internal coordination to move high-value deals.","Today"],
          ["blue","Audit HOD forecasts","Every SBU forecast must be supported by stage, value, probability, owner and next action.","Before weekly review"],
          ["green","Protect balanced performance","Strong results in one SBU must not hide serious underperformance elsewhere.","Ongoing"]
        ];
        return `<div class="card"><div class="chead"><h4>Interventions required</h4><span class="chip coral">Executive</span></div><div class="list">${list.map(([c,b,p,d])=>`<div class="arow"><span class="pd ${c}"></span><div><b>${esc(b)}</b><p>${esc(p)}</p></div><span class="due">${esc(d)}</span></div>`).join("")}</div></div>`;
      }
      function decisionsCard(){
        const items=[
          ["Pricing / discount approval","National Bank L&D — a 12% above-policy discount is requested to close KES 8.4M.","Decision"],
          ["Resource allocation","International needs one more BDE to hold pace — approve, defer or reallocate.","Decision"],
          ["Partnership sign-off","Co-delivery MOU with a university for the Academic SBU awaits signature.","Sign-off"],
          ["Product go / no-go","Eval360 v2 release — confirm the launch date or hold.","Go / No-go"]
        ];
        return `<div class="card"><div class="chead"><h4>Decisions requiring executive attention</h4><span class="chip gold">${items.length} pending</span></div><div class="list">${items.map(([t,d,tag])=>`<div class="arow"><span class="pd amber"></span><div><b>${esc(t)}</b><p>${esc(d)}</p></div><span class="chip slate">${esc(tag)}</span></div>`).join("")}</div></div>`;
      }
      function teamTable(){
        const avatarCols=["var(--slate)","#2f8f88","var(--brand)","var(--violet)","var(--gold)"];
        const p=period();
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>SBU</th><th>Target</th><th>Cleared</th><th>Attainment</th><th>Collection</th><th>Status / response</th></tr></thead><tbody>${B.sbus.map((d,i)=>{const a=d.actual/d.target;const exp=d.target*(p.elapsed/p.working);const st=d.actual>=exp?"green":d.actual>=exp*.85?"amber":"red";const lbl=st==="green"?"On pace":st==="amber"?"At risk":"Behind pace";const resp=st==="red"?"Recovery plan + daily monitoring":st==="amber"?"Corrective action within 24h":"Protect quality; pursue stretch";const ini=d.name.split(/\s+/).map(x=>x[0]).slice(0,2).join("");return `<tr><td><div class="prow"><span class="a" style="background:${avatarCols[i%avatarCols.length]}">${ini}</span><div><b><span data-scope="bdo-${i}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(d.name)}</span></b><span>${esc(d.leader)}</span></div></div></td><td class="num">${kMoney(d.target)}</td><td class="num">${kMoney(d.actual)}</td><td><span class="mini-track"><div style="width:${clamp(a*100,0,100)}%;background:${scol(st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(a,0)}</b></td><td class="num">${pct(d.collection,0)}</td><td><span class="sbadge s${st[0]}"><span class="dot"></span>${lbl}</span><div style="font-size:10.5px;color:var(--muted);margin-top:5px">${resp}</div></td></tr>`;}).join("")}</tbody></table></div></div>`;
      }

      /* ---------- executive master view (BDM request) ---------- */
      function execRevenueBreakdown(){
        const shortName={"International":"Int'l","Virtual":"Virtual","Corporate":"Corporate","Digital Solutions":"Digital","Academic":"Academic"};
        const data=B.sbus.map(d=>({name:shortName[d.name]||d.name,closed:d.actual,open:d.pipeline}));
        const max=Math.max(...data.map(d=>d.closed+d.open))*1.14;
        const w=640,h=250,pd=34,base=h-pd-16,plot=base-pd,step=(w-2*pd)/data.length,bw=52;
        const bars=data.map((d,i)=>{const cx=pd+step*i+step/2,x=cx-bw/2;const ch=d.closed/max*plot,oh=d.open/max*plot;return `<g><rect x="${x.toFixed(1)}" y="${(base-ch).toFixed(1)}" width="${bw}" height="${Math.max(0,ch).toFixed(1)}" rx="3" fill="var(--jade)"/><rect x="${x.toFixed(1)}" y="${(base-ch-oh).toFixed(1)}" width="${bw}" height="${Math.max(0,oh).toFixed(1)}" rx="3" fill="#4d8bd6"/><text x="${cx.toFixed(1)}" y="${base+16}" text-anchor="middle">${esc(d.name)}</text><text x="${cx.toFixed(1)}" y="${(base-ch-oh-6).toFixed(1)}" text-anchor="middle" style="font-weight:800;fill:var(--ink)">${kMoney(d.closed+d.open)}</text></g>`;}).join("");
        return `<div class="card"><div class="chead"><div><h4>Revenue breakdown</h4><p>Open pipeline vs closed-won revenue, by department.</p></div><span class="segmented">${["Monthly","Quarterly","YTD"].map((f,i)=>`<button class="seg${i===0?" on":""}" type="button">${f}</button>`).join("")}</span></div>
          <svg class="chart" viewBox="0 0 ${w} ${h}" style="height:250px" role="img" aria-label="Revenue breakdown by department">${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${pd}" y1="${(pd+t*plot).toFixed(1)}" x2="${w-pd}" y2="${(pd+t*plot).toFixed(1)}"/>`).join("")}${bars}</svg>
          <div class="legend"><span class="lg"><i style="background:var(--jade)"></i>Closed-won</span><span class="lg"><i style="background:#4d8bd6"></i>Open pipeline</span></div></div>`;
      }
      function execTargetProgress(){
        const p=period();
        const rows=B.sbus.map(d=>{const a=d.actual/d.target;const exp=d.target*(p.elapsed/p.working);const st=d.actual>=exp?"green":d.actual>=exp*.85?"amber":"red";return `<div class="tvp"><div class="tvp-top"><b>${esc(d.name)}</b><span class="chip ${st==="green"?"jade":st==="amber"?"amber":"coral"}">${pct(a,0)}</span></div><div class="track2"><div class="fill2" style="width:${clamp(a*100,0,100)}%;background:${scol(st)}"></div></div><div class="tvp-sub">${kMoney(d.actual)} / ${kMoney(d.target)}</div></div>`;}).join("");
        return `<div class="card"><div class="chead"><div><h4>Target vs actual — by department</h4><p>Closed revenue against each departmental quota.</p></div><span class="chip slate">Colour = pace</span></div><div class="tvp-grid">${rows}</div></div>`;
      }
      function execTopDeals(){
        const deals=[
          ["National Bank L&D framework","National Bank","Corporate",8400000,"Negotiation","Edwin Otieno"],
          ["Ministry M&E rollout — Botswana","Ministry of Finance","International",5800000,"Proposal / RFP","Erick Ndiema"],
          ["Government planning — Eval360","Government Planning Unit","Digital Solutions",4200000,"RFP qualified","Alein Kagunza"],
          ["Manufacturing group appraisal","Manufacturing Group","Digital Solutions",3200000,"Approval","Alein Kagunza"],
          ["Regional NGO consortium","NGO Consortium","Corporate",2800000,"Discovery","Edwin Otieno"],
          ["Corporate L&D partner","Safaricom","Virtual",2400000,"Negotiation","Francisca Ing'aa"],
          ["University staff cohort","Kenyatta University","Virtual",2400000,"Proposal","Francisca Ing'aa"],
          ["Central Bank data analysis","Central Bank — Sierra Leone","International",1900000,"Discovery","Erick Ndiema"],
          ["College network CPD","College Network","Academic",1600000,"Proposal","Hellen Letting"],
          ["Recurring digital account","AAR Insurance","Digital Solutions",1100000,"Renewal","Alein Kagunza"]
        ].sort((a,b)=>b[3]-a[3]);
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>#</th><th>Deal</th><th>Account</th><th>Department</th><th>Value</th><th>Stage</th><th>Owner</th></tr></thead><tbody>${deals.map((r,i)=>`<tr><td class="num">${i+1}</td><td><b>${esc(r[0])}</b></td><td>${esc(r[1])}</td><td><span class="stage-chip">${esc(r[2])}</span></td><td class="num">${kMoney(r[3])}</td><td>${esc(r[4])}</td><td>${esc(r[5])}</td></tr>`).join("")}</tbody></table></div></div>`;
      }

      /* ---------- views ---------- */
      function vCommand(){
        const ps=pace();
        return `${strategyStrip()}
          <section class="hero">
            <div class="card"><div class="chead"><h4>Enterprise scorecard</h4><span class="pace-pill ${ps.status==="green"?"pg":ps.status==="amber"?"pa":"pr"}"><span class="dot"></span>${ps.label} · pace ${pct(ps.ratio,0)}</span></div>${kpiBlock()}</div>
            ${progressCard()}
          </section>

          <div class="section-tag"><h3>Five-SBU performance</h3><span>The whole picture — click any SBU to drill into that department</span><div class="rule"></div></div>
          ${teamTable()}

          <div class="section-tag"><h3>Revenue trajectory &amp; strategic accounts</h3><span>Month-end forecast and the highest-value open accounts company-wide</span><div class="rule"></div></div>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Revenue pace &amp; month-end forecast</h4><span class="chip jade">${kMoney(B.forecast)} forecast</span></div>${trendSVG()}<div style="font-size:11.5px;color:var(--muted);margin-top:10px">The forecast moves whenever stage, probability, payment date or cleared revenue changes.</div></div>
            ${execRevenueBreakdown()}
          </section>
          ${execTopDeals()}

          <div class="section-tag"><h3>Where to intervene</h3><span>The few things that need leadership, resources or a decision — now</span><div class="rule"></div></div>
          <section class="grid-2">
            ${actionsCard()}
            ${decisionsCard()}
          </section>`;
      }

      function vPipeline(){
        const fmax=B.funnel[0][1];const smax=Math.max(...B.sources.map(s=>s[1]));
        const stale=[["5 hot leads have no action today","red"],["3 proposals have no confirmed review date","amber"],["11 payment promises are overdue","amber"]];
        return `
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Acquisition &amp; conversion funnel</h4><span class="chip slate">Live funnel</span></div><div class="funnel">${B.funnel.map(([l,n],i)=>`<div class="fr"><label>${esc(l)}</label><div class="fbar"><div style="width:${Math.max(9,n/fmax*100)}%">${nf.format(n)}</div></div><span class="cv">${i?Math.round(n/B.funnel[i-1][1]*100)+"%":"100%"}</span></div>`).join("")}</div></div>
            <div class="card"><div class="chead"><h4>Lead-source contribution</h4><span class="chip slate">Source ROI</span></div>${B.sources.map(([n,v])=>`<div class="src"><label>${esc(n)}</label><div class="sb"><div style="width:${v/smax*100}%"></div></div><b>${v}%</b></div>`).join("")}</div>
          </section>
          <div class="card"><div class="chead"><h4>Stale-lead alerts</h4><span class="chip coral">${stale.length} flagged</span></div><div class="list">${stale.map(([x,c])=>`<div class="arow"><span class="pd ${c}"></span><div><b>${esc(x)}</b><p>Open the filtered list and assign the next action.</p></div><span class="abtn hot">Open</span></div>`).join("")}</div></div>`;
      }

      function vReport(){
        const p=period();
        const sbusGreen=B.sbus.filter(d=>d.actual>=d.target*(p.elapsed/p.working)).length;
        const fields=[
          ["Organization daily revenue target","number",Math.round(B.target/p.working)],
          ["Actual cleared revenue today","number",Math.round(B.actual/p.elapsed)],
          ["SBUs at 80%+ pace","number",sbusGreen],
          ["Strategic-account meetings","number",4],
          ["BDM personal revenue MTD","number",B.personalActual],
          ["Consolidated qualified pipeline","number",B.pipeline],
          ["Proposals / tenders at risk","number",3],
          ["Collections requiring escalation","number",7],
          ["SBU performance summary","textarea",B.sbus.map(d=>`${d.name}: ${kMoney(d.actual)} / ${kMoney(d.target)}; forecast ${kMoney(d.forecast)}`).join("\n")],
          ["Strategic accounts and blocked deals","textarea","Account, value, stage, owner, blocker, executive action and next date."],
          ["HOD coaching / recovery decisions","textarea","Named HOD, issue, action, deadline and review point."],
          ["CEO decisions required","textarea","Budget, pricing, executive access, technology, legal, payment or capacity decision."]
        ];
        const fieldHTML=f=>`<div class="field ${f[1]==="textarea"?"span2":""}"><label>${esc(f[0])}</label>${f[1]==="textarea"?`<textarea data-label="${esc(f[0])}">${esc(f[2])}</textarea>`:`<input data-label="${esc(f[0])}" type="number" value="${esc(f[2])}">`}</div>`;
        const nums=fields.filter(f=>f[1]==="number").map(fieldHTML).join("");
        const texts=fields.filter(f=>f[1]==="textarea").map(fieldHTML).join("");
        return `
          <div class="card"><div class="chead"><h4>BDM consolidated commercial report</h4><span class="chip jade">Auto-prefilled</span></div>
            <div id="reportForm">
              <div class="form-sub">Today's numbers <i>· auto-prefilled</i></div>
              <div class="form-grid">${nums}</div>
              <div class="form-sub" style="margin-top:18px">Your narrative <i>· the human judgement</i></div>
              <div class="form-grid">${texts}</div>
            </div>
            <div class="report-actions"><button class="tbtn solid" id="genReport" type="button">Generate report summary</button><button class="tbtn" id="dlReport" type="button">Download</button><button class="tbtn" id="clrReport" type="button">Clear narrative</button></div>
          </div>
          <div class="card"><div class="chead"><h4>Generated management summary</h4><span class="chip jade">Evidence-linked</span></div><div id="reportPreview" class="report-preview">Select "Generate report summary" to compile the dashboard data and your explanations.</div></div>
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

      /* ---------- people model + drill-down (CEO can open anyone) ---------- */
      const pInitials=n=>String(n||"").trim().split(/\s+/).map(x=>x[0]).slice(0,2).join("").toUpperCase();
      function allPeople(){
        const list=[{key:"bdm",role:"BDM",name:B.bdmName,ini:B.bdmInitials,sbu:"All five SBUs",target:B.target,actual:B.actual,pipeline:B.pipeline,collection:B.collection,forecast:B.forecast}];
        B.sbus.forEach((s,si)=>{
          list.push({key:"bdo-"+si,role:"BDO",name:s.leader,ini:pInitials(s.leader),sbu:s.name,target:s.target,actual:s.actual,pipeline:s.pipeline,collection:s.collection,forecast:s.forecast,sbuIndex:si});
          (s.reps||[]).forEach((r,ri)=>list.push({key:"bde-"+si+"-"+ri,role:"BDE",name:r.name,ini:pInitials(r.name),sbu:s.name,title:r.title,target:r.target,actual:r.actual,pipeline:r.pipeline,collection:r.collection,units:r.units}));
        });
        return list;
      }
      function personByKey(k){return allPeople().find(p=>p.key===k)||null;}
      function paceOf(p){const per=period();const exp=p.target*(per.elapsed/per.working);const ratio=exp?p.actual/exp:0;const st=p.actual>=exp?"green":p.actual>=exp*.85?"amber":"red";return {ratio,st,label:st==="green"?"On pace":st==="amber"?"At risk":"Behind pace"};}

      function vPeople(){
        const people=allPeople();
        const leaders=people.filter(p=>p.role==="BDM"||p.role==="BDO").sort((a,b)=>(b.actual/b.target)-(a.actual/a.target));
        const lead=leaders.map((p,i)=>{const a=p.actual/p.target;const pc=paceOf(p);return `<tr><td class="num">${i+1}</td><td><div class="prow"><span class="a" data-scope="${p.key}" style="cursor:pointer">${esc(p.ini)}</span><div><b><span data-scope="${p.key}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(p.name)}</span></b><span>${esc(p.sbu)}</span></div></div></td><td><span class="chip slate">${p.role}</span></td><td class="num">${kMoney(p.target)}</td><td class="num">${kMoney(p.actual)}</td><td><span class="mini-track"><div style="width:${clamp(a*100,0,100)}%;background:${scol(pc.st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(a,0)}</b></td><td class="num">${kMoney(p.pipeline)}</td><td><span class="sbadge s${pc.st[0]}"><span class="dot"></span>${pc.label}</span></td></tr>`;}).join("");
        const deptCards=B.sbus.map((s,si)=>{const reps=(s.reps||[]);const inner=reps.map((r,ri)=>{const a=r.actual/r.target;const pc=paceOf(r);return `<div class="arow" data-scope="bde-${si}-${ri}" style="cursor:pointer"><span class="a" style="background:var(--slate);width:30px;height:30px;border-radius:8px;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800">${esc(pInitials(r.name))}</span><div><b>${esc(r.name)}</b><p>${esc(r.title||"BDE")} · ${kMoney(r.actual)} / ${kMoney(r.target)}</p></div><span class="chip ${pc.st==="green"?"jade":pc.st==="amber"?"amber":"coral"}">${pct(a,0)}</span></div>`;}).join("");
          return `<div class="card"><div class="chead"><h4><span data-scope="bdo-${si}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(s.name)}</span></h4><span class="chip slate">${esc(s.leader)}</span></div><div class="list">${inner||'<div class="arow"><div><b>No reps listed</b></div></div>'}</div></div>`;}).join("");
        return `
          <div class="section-tag"><h3>Leadership scorecard</h3><span>BDM and department heads, ranked by attainment — click anyone to open their view</span><div class="rule"></div></div>
          <div class="card tight"><div class="table-wrap"><table><thead><tr><th>#</th><th>Person</th><th>Role</th><th>Target</th><th>Cleared</th><th>Attainment</th><th>Pipeline</th><th>Status</th></tr></thead><tbody>${lead}</tbody></table></div></div>
          <div class="section-tag"><h3>Departments &amp; executives</h3><span>Every BDE under each department — click to drill into a person</span><div class="rule"></div></div>
          <section class="grid-3">${deptCards}</section>`;
      }

      function vPerson(p){
        const a=p.actual/p.target;const pc=paceOf(p);
        const kpis=[
          ["Target",kMoney(p.target),"Approved target","var(--slate)"],
          ["Cleared revenue",kMoney(p.actual),pct(a)+" of target","var(--jade)"],
          ["Qualified pipeline",kMoney(p.pipeline),(p.pipeline/p.target).toFixed(1)+"× coverage","var(--slate)"],
          ["Collection",pct(p.collection,0),"cleared vs invoiced","var(--brand)"]
        ];
        if(p.role==="BDE"&&p.units!=null)kpis.push(["Volume",nf.format(p.units),"units this period","var(--slate)"]);
        else kpis.push(["Month-end forecast",kMoney(p.forecast||p.actual),"projected","var(--gold)"]);
        const kpiRow=`<div class="kpis">${kpis.map(([l,v,m,ac])=>`<div class="kpi" style="--acc:${ac}"><div class="lab">${l}</div><div class="val num">${v}</div><div class="meta">${m}</div></div>`).join("")}</div>`;
        let extra="";
        if(p.role==="BDM"){extra=`<div class="section-tag"><h3>Five-SBU performance</h3><span>Consolidated across all departments</span><div class="rule"></div></div>${teamTable()}`;}
        else if(p.role==="BDO"&&p.sbuIndex!=null){const reps=(B.sbus[p.sbuIndex].reps||[]);const rows=reps.map((r,ri)=>{const ra=r.actual/r.target;const rp=paceOf(r);return `<tr><td><div class="prow"><span class="a" style="background:var(--slate)">${esc(pInitials(r.name))}</span><div><b><span data-scope="bde-${p.sbuIndex}-${ri}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(r.name)}</span></b><span>${esc(r.title||"BDE")}</span></div></div></td><td class="num">${kMoney(r.target)}</td><td class="num">${kMoney(r.actual)}</td><td><span class="mini-track"><div style="width:${clamp(ra*100,0,100)}%;background:${scol(rp.st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(ra,0)}</b></td><td class="num">${kMoney(r.pipeline)}</td><td><span class="sbadge s${rp.st[0]}"><span class="dot"></span>${rp.label}</span></td></tr>`;}).join("");extra=`<div class="section-tag"><h3>${esc(p.sbu)} team</h3><span>Executives reporting to ${esc(p.name)} — click to drill in</span><div class="rule"></div></div><div class="card tight"><div class="table-wrap"><table><thead><tr><th>Executive</th><th>Target</th><th>Cleared</th><th>Attainment</th><th>Pipeline</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;}
        else{extra=`<div class="card"><div class="chead"><h4>Notes</h4><span class="chip slate">Prototype</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Individual field activity, deals and daily reports appear here once live data is wired. For now this shows ${esc(p.name)}'s headline numbers scoped from the organization dataset.</p></div>`;}
        return `
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
            <button class="tbtn" data-scope="org" type="button">← Back to organization</button>
            <div class="prow"><span class="a" style="background:linear-gradient(150deg,var(--brand),var(--gold))">${esc(p.ini)}</span><div><b>${esc(p.name)}</b><span>${p.role} · ${esc(p.sbu)}</span></div></div>
            <span class="pace-pill ${pc.st==="green"?"pg":pc.st==="amber"?"pa":"pr"}" style="margin-left:auto"><span class="dot"></span>${pc.label} · pace ${pct(pc.ratio,0)}</span>
          </div>
          ${kpiRow}
          ${extra}`;
      }

      function populateScope(){
        const people=allPeople();
        const opt=p=>`<option value="${p.key}">${esc(p.name)} — ${esc(p.sbu)}</option>`;
        el("scopeSelect").innerHTML=`<option value="org">Whole organization</option>`
          +`<optgroup label="Leadership">${people.filter(p=>p.role==="BDM"||p.role==="BDO").map(opt).join("")}</optgroup>`
          +`<optgroup label="Executives (BDE)">${people.filter(p=>p.role==="BDE").map(opt).join("")}</optgroup>`;
      }
      function syncScope(){
        const scoped=state.scope&&state.scope!=="org";
        el("scopeSelect").value=scoped?state.scope:"org";
        root.querySelectorAll("#tabNav .tab").forEach(t=>t.classList.toggle("active",!scoped&&t.dataset.v===state.view));
      }

      function render(){
        const scoped=(state.scope&&state.scope!=="org")?personByKey(state.scope):null;
        if(scoped){el("workspace").innerHTML=vPerson(scoped);}
        else{const v=state.view;el("workspace").innerHTML=v==="command"?vCommand():v==="people"?vPeople():vPipeline();}
        syncScope();
        root.querySelectorAll("[data-scope]").forEach(x=>x.addEventListener("click",()=>{state.scope=x.getAttribute("data-scope");render();window.scrollTo({top:0,behavior:"smooth"});}));
      }
      function bindReport(){
        el("genReport").addEventListener("click",genReport);
        el("dlReport").addEventListener("click",()=>{genReport();const t=el("reportPreview").textContent;const b=new Blob([t],{type:"text/plain"});const a=document.createElement("a");a.href=URL.createObjectURL(b);a.download="Vantage_BDE_"+period().label.replace(/\s+/g,"_")+"_Report.txt";a.click();URL.revokeObjectURL(a.href);});
        el("clrReport").addEventListener("click",()=>root.querySelectorAll("#reportForm textarea").forEach(x=>x.value=""));
      }
      function genReport(){
        const lines=["VANTAGE AFRICA — BDE DAILY REPORT","Period: "+period().label,"Consultant: "+B.name+" | "+B.title+" · "+B.dept,""];
        root.querySelectorAll("#reportForm input,#reportForm textarea").forEach(x=>lines.push(x.dataset.label+": "+(x.value.trim()||"—")));
        const att=B.actual/B.target;lines.push("");lines.push("Dashboard position: "+kMoney(B.actual)+" cleared against "+kMoney(B.target)+" ("+pct(att)+").");
        lines.push("Qualified pipeline: "+kMoney(B.pipeline)+". Collection: "+pct(B.collection,0)+".");
        lines.push("Commission estimate: "+kMoney(commission().current)+".");
        lines.push("All figures subject to CRM evidence and Finance verification.");
        el("reportPreview").textContent=lines.join("\n");
      }

      populateScope();
      el("scopeSelect").addEventListener("change",e=>{state.scope=e.target.value;render();window.scrollTo({top:0,behavior:"smooth"});});
      el("periodSelect").innerHTML=periods.map((p,i)=>`<option value="${i}" ${i===state.p?"selected":""}>${p.label}</option>`).join("");
      el("periodSelect").addEventListener("change",e=>{state.p=+e.target.value;render();});
      root.querySelectorAll("#tabNav .tab[data-v]").forEach(a=>a.addEventListener("click",()=>{state.scope="org";state.view=a.dataset.v;render();}));
      el("themeBtn").addEventListener("click",()=>{const dark=root.classList.toggle("theme-dark");el("themeBtn").textContent=dark?"☀ Light":"🌙 Dark";});

      render();
    })();
    </script>
  </div>
</section>

<?php require_once 'footer.php'; ?>
