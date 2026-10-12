<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>UD Krupuk Lancar — POS</title>

    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('img/favicon-64.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/logo-180.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">

    {{-- @vite memuat Bootstrap (tema krupuk via app.scss + pos.scss) dan seluruh
         JS POS (window.bootstrap.Modal/Collapse/Toast, pos-ui, pos-pay, pos-scroll,
         grafik Ringkasan) sebagai satu bundle. --}}
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
<div id="pos-root">
    {{-- Kontainer notifikasi toast (posToast di pos-ui.js) --}}
    <div class="toast-container position-fixed top-0 end-0 p-3" id="posToastHost" style="z-index:1090;"></div>

    @if(session('success'))
        <div style="padding:12px 28px 0;"><div class="alert alert-success mb-0" role="alert">{{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div style="padding:12px 28px 0;"><div class="alert alert-danger mb-0" role="alert">{{ session('error') }}</div></div>
    @endif

    @php
        $baseParams = ['bulan' => $activeMonth, 'tx_date' => $txDate];
    @endphp

    <nav class="pos-nav">
        <a href="{{ route('pos', $baseParams) }}" class="pos-brand" title="UD Krupuk Lancar">
            <img src="{{ asset('img/logo-180.png') }}" alt="" class="pos-brand-logo" width="40" height="40">
            <span class="pos-brand-text">
                <span class="pos-brand-name">UD Krupuk Lancar</span>
                <span class="pos-brand-sub">Point of Sale</span>
            </span>
        </a>
        <div class="tabs">
            <a href="{{ route('pos', array_merge($baseParams, ['tab' => 'transaksi'])) }}" class="tab-btn {{ $tab === 'transaksi' ? 'active' : '' }}">Transaksi</a>
            <a href="{{ route('pos', array_merge($baseParams, ['tab' => 'produk'])) }}" class="tab-btn {{ $tab === 'produk' ? 'active' : '' }}">Produk</a>
            <a href="{{ route('pos', array_merge($baseParams, ['tab' => 'hasil'])) }}" class="tab-btn {{ $tab === 'hasil' ? 'active' : '' }}">Hasil</a>
            <a href="{{ route('pos', array_merge($baseParams, ['tab' => 'ringkasan'])) }}" class="tab-btn {{ $tab === 'ringkasan' ? 'active' : '' }}">Ringkasan</a>
        </div>
        <div class="date-switch">
            <input type="date" id="activeDateInput" value="{{ $txDate }}"
                   title="Tanggal aktif — semua tab mengikuti tanggal ini"
                   onchange="window.location.href='{{ route('pos', ['tab' => $tab]) }}&tx_date=' + this.value">
        </div>
    </nav>

    <datalist id="customerNames">
        @foreach ($allCustomerNames as $n)
            <option value="{{ $n }}"></option>
        @endforeach
    </datalist>

    <main>
        {{-- Semua panel selalu dirender; yang aktif ditandai kelas .active (gaya: pos.scss
             Bagian "tipografi, switch tanggal, navigasi tab, panel"). Sisipkan sesuai urutan tab. --}}
        <div class="tab-panel {{ $tab === 'transaksi' ? 'active' : '' }}" id="tab-transaksi">
            @include('pos._tab_transaksi')
        </div>

        <div class="tab-panel {{ $tab === 'produk' ? 'active' : '' }}" id="tab-produk">
            @include('pos._tab_produk')
        </div>

        <div class="tab-panel {{ $tab === 'hasil' ? 'active' : '' }}" id="tab-hasil">
            @include('pos._tab_hasil')
        </div>

        <div class="tab-panel {{ $tab === 'ringkasan' ? 'active' : '' }}" id="tab-ringkasan">
            @include('pos._tab_ringkasan')
        </div>
    </main>

    {{-- Modal global (menumpuk di atas panel mana pun): modal hutang pelanggan & modal Bayar. --}}
    @include('pos._modal_hutang')
    @include('pos._modal_bayar')
</div>
</body>
</html>
