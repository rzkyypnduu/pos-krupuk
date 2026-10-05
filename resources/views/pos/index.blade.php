<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS Krupuk</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">

    @include('pos._style')

    {{-- Rantai pembayaran (urutan penting, semua defer): window.POS_PAY disuntik oleh
         _modal_bayar -> pos-pay.js mendaftarkan Alpine.store('pay') saat event alpine:init
         -> Alpine CDN memulai komponen. pos-scroll.js = efek scroll antar tab. --}}
    <script src="{{ asset('js/pos-pay.js') }}?v={{ filemtime(public_path('js/pos-pay.js')) }}" defer></script>
    <script src="{{ asset('js/pos-scroll.js') }}?v={{ filemtime(public_path('js/pos-scroll.js')) }}" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
<div id="pos-root" x-data>
    @if(session('success'))
        <div style="padding:12px 28px 0;"><div class="flash-success">{{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div style="padding:12px 28px 0;"><div class="flash-error">{{ session('error') }}</div></div>
    @endif

    @php
        $baseParams = ['bulan' => $activeMonth, 'tx_date' => $txDate];
    @endphp

    <nav class="tabs">
        <a href="{{ route('pos', array_merge($baseParams, ['tab' => 'transaksi'])) }}" class="tab-btn {{ $tab === 'transaksi' ? 'active' : '' }}">Transaksi Harian</a>
        <a href="{{ route('pos', array_merge($baseParams, ['tab' => 'produk'])) }}" class="tab-btn {{ $tab === 'produk' ? 'active' : '' }}">Master Produk</a>
        <a href="{{ route('pos', array_merge($baseParams, ['tab' => 'hasil'])) }}" class="tab-btn {{ $tab === 'hasil' ? 'active' : '' }}">Hasil</a>
        <a href="{{ route('pos', array_merge($baseParams, ['tab' => 'ringkasan'])) }}" class="tab-btn {{ $tab === 'ringkasan' ? 'active' : '' }}">Ringkasan</a>
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
        {{-- Semua panel selalu dirender; yang aktif ditandai kelas .active (gaya: _style
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
