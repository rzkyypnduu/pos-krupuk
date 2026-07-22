<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS Krupuk</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">

    @livewireStyles

    <style>
        #pos-root, #pos-root * { box-sizing: border-box; }
        #pos-root {
            --bg: #FFFFFF; --surface: #FFFFFF; --ink: #000000; --ink-soft: #333333;
            --accent: #C06014; --accent-dark: #8B4513; --line: #999999;
            --debt: #CC0000; --debt-bg: #FFE0E0; --paid: #006600; --paid-bg: #D0F0D0;
            --mono: 'IBM Plex Mono', 'SF Mono', Consolas, monospace;
            font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 18px; background: var(--bg); color: var(--ink); min-height: 100vh; padding: 0 0 60px 0;
        }
        #pos-root h1, #pos-root h2, #pos-root h3 { font-family: 'Fraunces', Georgia, serif; margin: 0; }
        #pos-root .month-switch { display:flex; align-items:center; gap:8px; flex-wrap:nowrap; background:#FFFFFF; border:2px solid var(--line); border-radius:9px; padding:6px 12px; flex-shrink:0; }
        #pos-root .month-switch label { margin:0; font-size:14px; white-space:nowrap; font-weight:700; color:var(--ink); }
        #pos-root .month-switch input[type="month"] { width:auto; padding:8px 10px; font-size:15px; border:2px solid var(--line); }
        #pos-root .month-switch .month-label { font-weight:700; font-size:15px; color: var(--accent-dark); white-space:nowrap; }
        #pos-root .month-nav-btn { font-family:inherit; background:var(--surface); border:2px solid var(--line); border-radius:6px; width:36px; height:36px; cursor:pointer; color:var(--ink); font-size:16px; flex-shrink:0; }
        #pos-root .month-nav-btn:hover { border-color: var(--accent); background:var(--accent); color:#FFFFFF; }
        #pos-root .month-switch input:disabled, #pos-root .month-nav-btn:disabled { opacity:.5; cursor:not-allowed; }
        #pos-root .tabs { display:flex; align-items:center; overflow-x:auto; gap:4px; padding: 8px 12px; background: #FFFFFF; border-bottom: 2px solid #000000; position: sticky; top: 0; z-index: 20; }
        #pos-root .tabs .month-switch { margin-left: auto; }
        #pos-root .tab-btn { font-family: inherit; font-size: 18px; font-weight: 700; color: #555555; background: transparent; border: none; padding: 14px 20px; cursor: pointer; border-bottom: 4px solid transparent; white-space: nowrap; flex-shrink:0; }
        #pos-root .tab-btn.active { color: #000000; border-bottom-color: var(--accent); }
        #pos-root .tab-btn:hover { color: #000000; }
        #pos-root main { padding: 24px 28px 0; width: 100%; margin: 0; }
        #pos-root .tab-panel { display:none; }
        #pos-root .tab-panel.active { display:block; }
        #pos-root .card { background: var(--surface); border: 2px solid var(--line); border-radius: 12px; padding: 24px; margin-bottom: 20px; }
        #pos-root .card h2 { font-size: 21px; margin-bottom: 16px; }
        #pos-root .card h3 { font-size: 17px; color: #000000; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 12px; font-weight:700; }
        #pos-root label { font-size: 18px; color: #000000; font-weight:700; display:block; margin-bottom:8px; }
        #pos-root input, #pos-root select { font-family: inherit; font-size: 20px; padding: 14px 16px; border: 2px solid var(--line); border-radius: 8px; background: #FFFFFF; color: var(--ink); width: 100%; }
        #pos-root input:focus, #pos-root select:focus { outline: 2px solid var(--accent); outline-offset: 1px; border-color: var(--accent); }
        #pos-root .field { margin-bottom: 12px; }
        #pos-root .field-row { display:flex; gap:12px; flex-wrap: wrap; }
        #pos-root .field-row > .field { flex: 1; min-width: 150px; }
        #pos-root .hint { font-size:15px; color: var(--ink-soft); margin: -4px 0 12px; }
        #pos-root .hint.debt-hint { color: var(--debt); font-weight:600; }
        #pos-root button.primary { font-family: inherit; font-weight: 700; font-size: 17px; background: var(--accent); color: #FFFFFF; border: none; padding: 14px 24px; border-radius: 8px; cursor: pointer; }
        #pos-root button.primary:hover { background: var(--accent-dark); }
        #pos-root button.ghost { font-family: inherit; font-weight: 600; font-size: 16px; background: #FFFFFF; color: var(--ink); border: 2px solid var(--line); padding: 11px 16px; border-radius: 7px; cursor: pointer; }
        #pos-root button.ghost:hover { border-color: #000000; background: #F0F0F0; }
        #pos-root button.danger { color: var(--debt); border-color: var(--debt); }
        #pos-root button.danger:hover { background: var(--debt-bg); }
        #pos-root table { width: 100%; border-collapse: collapse; font-size: 17px; }
        #pos-root th { text-align:left; font-size: 14px; text-transform: uppercase; letter-spacing: .03em; color: #000000; border-bottom: 3px solid #000000; padding: 12px 12px; font-weight:700; }
        #pos-root td { padding: 14px 12px; border-bottom: 2px solid #CCCCCC; vertical-align: top; }
        #pos-root tr:last-child td { border-bottom: none; }
        #pos-root tr.group-row td { background:#E8E0D0; font-weight:700; border-top: 3px solid #000000; }
        #pos-root .num { font-family: var(--mono); text-align: right; white-space: nowrap; }
        #pos-root .table-wrap { overflow-x:auto; }
        #pos-root .qty-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(110px,1fr)); gap: 8px; margin: 10px 0; }
        #pos-root .qty-item label { font-size: 15px; margin-bottom:5px; }
        #pos-root .qty-item input { padding: 11px 12px; font-size: 16.5px; }
        #pos-root .summary-line { display:flex; justify-content:space-between; padding: 10px 0; font-size: 17px; border-bottom: 1px dashed var(--line); }
        #pos-root .summary-line:last-child { border-bottom:none; }
        #pos-root .summary-line .val { font-family: var(--mono); font-weight:600; }
        #pos-root .summary-line.total { font-weight:700; font-size: 19px; padding-top:14px; }
        #pos-root .badge { display:inline-block; padding: 6px 14px; border-radius: 20px; font-size: 15px; font-weight:700; font-family: var(--mono); border:2px solid transparent; }
        #pos-root .badge.debt { background: var(--debt-bg); color: var(--debt); border-color: var(--debt); }
        #pos-root .badge.paid { background: var(--paid-bg); color: var(--paid); border-color: var(--paid); }
        #pos-root .badge.zero { background: #E0E0E0; color: #000000; border-color: #999999; }
        #pos-root .stat-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 12px; }
        #pos-root .stat-box { background: #FFFFFF; border: 2px solid var(--line); border-radius: 10px; padding: 17px 20px; }
        #pos-root .stat-box .label { font-size: 15px; color: #000000; text-transform:uppercase; letter-spacing:.03em; font-weight:700; }
        #pos-root .stat-box .value { font-family: var(--mono); font-size: 24px; font-weight:700; margin-top:5px; }
        #pos-root .stat-box.highlight { background: #000000; border-color: #000000; }
        #pos-root .stat-box.highlight .label { color: #CCCCCC; }
        #pos-root .stat-box.highlight .value { color: #FFFFFF; font-size: 28px; }
        #pos-root .empty { text-align:center; padding: 30px 14px; color: var(--ink-soft); font-size: 16px; }
        #pos-root .note { font-size: 15px; color: #000000; margin-top: 9px; line-height:1.55; }
        #pos-root .sub-section { border-top: 2px solid var(--line); padding-top: 14px; margin-top: 14px; }
        #pos-root .sub-section:first-child { border-top:none; padding-top:0; margin-top:0; }
        #pos-root .row-actions { display:flex; gap:6px; }
        #pos-root .warn-price { color: var(--debt); font-size: 13px; font-weight:700; }
        #pos-root .sack-row { display:flex; gap:8px; align-items:center; margin-bottom:7px; }
        #pos-root .sack-row input { flex:1; }
        #pos-root .sack-row .sack-remove-btn { flex-shrink:0; }
        #pos-root .section-header { margin: 36px 0 18px; padding-bottom: 10px; border-bottom: 3px solid #000000; }
        #pos-root .section-header h2 { font-size: 24px; color: #000000; margin: 0 0 6px 0; }
        #pos-root .section-header p { font-size: 16px; color: #333333; margin: 0; }
        #pos-root .section-header:first-child { margin-top: 0; }
        #hpSummaryTable th.mat-col { background: #E8E0D0; text-align: right; color:#000000; font-weight:700; border-bottom:2px solid #000000; }
        #hpSummaryTable td.mat-col { background: #FFFFFF; border-left: 2px solid #CCCCCC; cursor:pointer; }
        #hpSummaryTable td.mat-col:hover { background: #FFE8B0; }
        #hpSummaryTable td.mat-col.empty-cell { cursor: default; }
        #hpSummaryTable td.mat-col.empty-cell:hover { background: #FFFFFF; }
        #hpSummaryTable .hp-toolbar { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
        #pos-root .modal-backdrop { position:fixed; inset:0; z-index:50; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(44,32,22,.52); }
        #pos-root .modal-backdrop.open { display:flex; }
        #pos-root .debt-modal { width:min(100%,480px); padding:0; overflow:hidden; border:1px solid var(--line); border-radius:16px; background:var(--surface); box-shadow:0 24px 70px rgba(44,32,22,.28); }
        #pos-root .debt-modal-head { padding:22px 24px; color:#FFFFFF; background:linear-gradient(135deg,var(--accent-dark),var(--accent)); }
        #pos-root .debt-modal-head h2 { font-size:24px; color:#FFFFFF; }
        #pos-root .debt-modal-head p { margin:5px 0 0; font-size:15px; color:#FFFFFF; }
        #pos-root .debt-modal-body { padding:22px 24px 24px; }
        #pos-root .modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; }
        #pos-root .debt-modal .field { margin-bottom:15px; }
        #pos-root .debt-modal label { font-size:16px; margin-bottom:8px; }
        #pos-root .debt-modal input { min-height:50px; font-size:18px; }
        #pos-root .debt-modal .modal-actions { border-top:2px solid var(--line); padding-top:16px; margin-top:20px; }
        #pos-root .field-error { color: var(--debt); font-size: 14px; margin-top: 4px; font-weight:700; }
        #pos-root .pay-confirm-modal { width:min(100%,460px); padding:0; overflow:hidden; border:2px solid var(--line); border-radius:16px; background:var(--surface); box-shadow:0 24px 70px rgba(0,0,0,.28); }
        #pos-root .pay-confirm-head { padding:20px 24px; color:#FFFFFF; background:linear-gradient(135deg,var(--accent-dark),var(--accent)); }
        #pos-root .pay-confirm-head h2 { font-size:22px; color:#FFFFFF; }
        #pos-root .pay-confirm-head p { margin:5px 0 0; font-size:16px; color:#FFFFFF; }
        #pos-root .pay-confirm-body { padding:22px 24px 24px; }
        #pos-root .pay-confirm-body .modal-actions { border-top:2px solid var(--line); padding-top:16px; margin-top:20px; display:flex; justify-content:flex-end; gap:8px; }
        [x-cloak] { display: none !important; }
        /* ======= Transaksi Harian Redesign ======= */
        #pos-root .tx-layout { display:grid; grid-template-columns:1fr; gap:18px; }
        @media (min-width:992px) { #pos-root .tx-layout { grid-template-columns:1fr 330px; } }
        #pos-root .tx-layout > * { min-width:0; }

        #pos-root .tx-form-card { padding:0; overflow:hidden; }
        #pos-root .tx-form-head { padding:20px 24px 16px; background:var(--ink); color:#FFFFFF; display:flex; align-items:center; gap:12px; flex-wrap:wrap; justify-content:space-between; }
        #pos-root .tx-form-head h2 { font-size:19px; color:#FFFFFF; margin:0; }
        #pos-root .tx-form-head input[type="date"] { width:auto; padding:10px 14px; font-size:16px; background:rgba(255,255,255,.2); border:2px solid rgba(255,255,255,.4); color:#FFFFFF; border-radius:7px; }
        #pos-root .tx-form-head input[type="date"]:focus { outline:2px solid var(--accent); border-color:var(--accent); }
        #pos-root .tx-form-head input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(1); }

        #pos-root .tx-form-body { padding:20px 24px 24px; }

        #pos-root .tx-customer-row { display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap; }
        #pos-root .tx-customer-row .tx-name-field { flex:1; min-width:180px; }
        #pos-root .tx-customer-row .tx-name-field input { font-size:20px; padding:14px 16px; height:52px; }
        #pos-root .tx-customer-row .tx-debt-pill { padding:10px 16px; border-radius:24px; font-size:15px; font-weight:600; white-space:nowrap; margin-top:2px; }
        #pos-root .tx-customer-row .tx-debt-pill.zero { background:#E0E0E0; color:#000000; border:2px solid #999999; }
        #pos-root .tx-customer-row .tx-debt-pill.debt { background:var(--debt-bg); color:var(--debt); border:2px solid var(--debt); }
        #pos-root .tx-customer-row .tx-debt-pill.credit { background:var(--paid-bg); color:var(--paid); border:2px solid var(--paid); }

        #pos-root .tx-product-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:12px; margin:8px 0 4px; }
        #pos-root .product-card { background:#FFFFFF; border:2px solid var(--line); border-radius:10px; padding:16px 12px; text-align:center; transition:all .15s; }
        #pos-root .product-card:hover { border-color:var(--accent); box-shadow:0 2px 8px rgba(0,0,0,.2); }
        #pos-root .product-card.has-qty { border-color:var(--accent); border-width:3px; background:#FFF5E8; }
        #pos-root .product-card-name { font-weight:700; font-size:16px; margin-bottom:10px; color:#000000; }
        #pos-root .product-card-input { display:flex; align-items:center; gap:4px; }
        #pos-root .product-card-input .qty-btn { width:40px; height:40px; border-radius:8px; border:2px solid var(--line); background:var(--surface); cursor:pointer; font-size:20px; font-weight:700; display:flex; align-items:center; justify-content:center; color:var(--ink); flex-shrink:0; line-height:1; padding:0; }
        #pos-root .product-card-input .qty-btn:hover { background:var(--accent); color:#FFFFFF; border-color:var(--accent); }
        #pos-root .product-card-input .qty-input { flex:1; min-width:0; text-align:center; padding:10px 4px; font-size:20px; font-family:var(--mono); border:2px solid var(--line); border-radius:6px; background:#FFFFFF; -moz-appearance:textfield; }
        #pos-root .product-card-input .qty-input::-webkit-outer-spin-button, #pos-root .product-card-input .qty-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
        #pos-root .product-card-input .qty-input:focus { outline:2px solid var(--accent); outline-offset:1px; border-color:var(--accent); }
        #pos-root .product-card-subtotal { font-size:13px; color:var(--ink-soft); margin-top:8px; font-family:var(--mono); }

        #pos-root .tx-totals-bar { display:flex; gap:12px; margin:14px 0; flex-wrap:wrap; }
        #pos-root .tx-total-stat { flex:1; min-width:120px; background:#FFFFFF; border:2px solid var(--line); border-radius:10px; padding:14px 16px; text-align:center; }
        #pos-root .tx-total-stat .label { font-size:13px; text-transform:uppercase; letter-spacing:.04em; color:#000000; font-weight:700; }
        #pos-root .tx-total-stat .value { font-family:var(--mono); font-size:20px; font-weight:700; margin-top:4px; color:#000000; }
        #pos-root .tx-total-stat.highlight { background:#000000; border-color:#000000; }
        #pos-root .tx-total-stat.highlight .label { color:#CCCCCC; }
        #pos-root .tx-total-stat.highlight .value { color:#FFFFFF; font-size:23px; }

        #pos-root .tx-payment-row { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-start; padding:14px 0; border-top:1px solid var(--line); margin-top:6px; }
        #pos-root .tx-payment-row .tx-paid-group { flex:1; min-width:160px; }
        #pos-root .tx-payment-row .tx-paid-group .input-group { display:flex; gap:6px; }
        #pos-root .tx-payment-row .tx-paid-group .input-group input { flex:1; min-width:0; font-size:20px; padding:14px 16px; height:52px; }
        #pos-root .tx-payment-row .tx-paid-group .input-group .pas-btn { height:52px; padding:0 22px; border-radius:8px; border:2px solid var(--accent); background:var(--accent); color:#FFFFFF; font-weight:700; font-size:16px; cursor:pointer; flex-shrink:0; }
        #pos-root .tx-payment-row .tx-paid-group .input-group .pas-btn:hover { background:var(--accent-dark); border-color:var(--accent-dark); }
        #pos-root .tx-payment-row .tx-status-group { flex:1; min-width:160px; }
        #pos-root .tx-payment-row .tx-status-group .status-box { padding:12px 16px; border-radius:8px; font-size:16px; font-weight:700; min-height:52px; display:flex; align-items:center; border:2px solid transparent; }
        #pos-root .tx-payment-row .tx-status-group .status-box.debt { background:var(--debt-bg); color:var(--debt); border-color:var(--debt); }
        #pos-root .tx-payment-row .tx-status-group .status-box.paid { background:var(--paid-bg); color:var(--paid); border-color:var(--paid); }
        #pos-root .tx-payment-row .tx-status-group .status-box.zero { background:#E0E0E0; color:#000000; border-color:#999999; }

        #pos-root .tx-note { margin-top:2px; }
        #pos-root .tx-note input { font-size:17px; padding:12px 16px; }

        #pos-root .tx-edit-banner { background:#FFF0C0; border:2px solid #C09830; border-radius:8px; padding:13px 16px; font-size:16px; color:#5A3A00; display:flex; align-items:center; gap:10px; margin-top:12px; font-weight:600; }
        #pos-root .tx-edit-banner button { flex-shrink:0; }

        #pos-root .tx-actions { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-top:14px; }
        #pos-root .tx-actions p { font-size:12.5px; color:var(--ink-soft); margin:0; }

        /* Sidebar cards */
        #pos-root .tx-sidebar > .card { padding:18px 20px; }
        #pos-root .tx-sidebar h2 { font-size:17px; margin-bottom:12px; }
        #pos-root .tx-table { font-size:20px; }
        #pos-root .tx-table th { font-size:16px; padding:16px 16px; }
        #pos-root .tx-table td { padding:18px 16px; }
        #pos-root .tx-table .row-actions { display:flex; gap:10px; }
        #pos-root .tx-table .row-actions button { font-size:16px; padding:12px 18px; }
        #pos-root .tx-sidebar-stat-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        #pos-root .tx-sidebar-stat { padding:13px 15px; border-radius:8px; background:#FFFFFF; border:2px solid var(--line); }
        #pos-root .tx-sidebar-stat .num { font-family:var(--mono); font-weight:700; font-size:19px; margin-top:2px; }
        #pos-root .tx-sidebar-stat .num.green { color:var(--paid); }
        #pos-root .tx-sidebar-stat .num.red { color:var(--debt); }
        #pos-root .tx-sidebar-stat .lbl { font-size:13px; color:#000000; text-transform:uppercase; letter-spacing:.03em; font-weight:700; }

        #pos-root .tx-sidebar .tx-expense-form { display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
        #pos-root .tx-sidebar .tx-expense-form input { font-size:16px; padding:12px 14px; flex:1; min-width:80px; }
        #pos-root .tx-sidebar .tx-expense-form button { flex-shrink:0; padding:12px 16px; font-size:15px; }

        @media (max-width: 520px) {
            #pos-root .modal-backdrop { align-items:flex-end; padding:0; }
            #pos-root .debt-modal { width:100%; border-radius:18px 18px 0 0; max-height:92vh; overflow:auto; }
            #pos-root .debt-modal-head, #pos-root .debt-modal-body { padding-left:18px; padding-right:18px; }
            #pos-root .modal-actions { flex-direction:column-reverse; }
            #pos-root .modal-actions button { width:100%; }
            #pos-root .tx-form-head { flex-direction:column; align-items:stretch; }
            #pos-root .tx-form-body { padding:14px; }
            #pos-root .tx-product-grid { grid-template-columns:repeat(auto-fill, minmax(100px,1fr)); gap:7px; }
            #pos-root .product-card { padding:9px; }
            #pos-root .tx-payment-row { flex-direction:column; }
            #pos-root .tx-totals-bar { flex-direction:column; }
            #pos-root .tx-total-stat { text-align:left; display:flex; justify-content:space-between; align-items:center; padding:10px 13px; }
            #pos-root .tx-total-stat .value { margin-top:0; font-size:15px; }
            #pos-root .tx-total-stat.highlight .value { font-size:17px; }
            #pos-root .tx-sidebar-stat-grid { grid-template-columns:1fr 1fr; }
            #pos-root .tx-actions { flex-direction:column; align-items:stretch; }
            #pos-root .tx-actions button { width:100%; }
        }
    </style>
</head>
<body>
    {{ $slot }}

    @livewireScripts
</body>
</html>
