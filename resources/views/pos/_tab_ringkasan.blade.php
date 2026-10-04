@if($ringkasanData)
    <div class="card">
        <div style="margin-bottom:14px;"><h2 style="margin-bottom:4px;">Ringkasan neraca &amp; stok</h2><div class="hint" style="margin:0;">Ringkasan harian (stok, hutang, saldo) pindah ke tab Hasil. Neraca di bawah mengikuti tanggal aktif di bagian atas halaman.</div></div>
        <h3 style="margin-bottom:14px;">Tanggal: <span>{{ $ringkasanData['txDate'] }}</span></h3>
        <div class="stat-grid">
            <div class="stat-box"><div class="label">Total stok minyak</div><div class="value">{{ rupiah($ringkasanData['totalOil']) }}</div></div>
            <div class="stat-box"><div class="label">Total manajemen stok barang</div><div class="value">{{ rupiah($ringkasanData['totalStockMgmt']) }}</div></div>
            <div class="stat-box"><div class="label">Total sisa barang</div><div class="value">{{ rupiah($ringkasanData['totalRemain']) }}</div></div>
            <div class="stat-box"><div class="label">Total hutang pelanggan</div><div class="value">{{ rupiah($ringkasanData['totalHutangPel']) }}</div></div>
            <div class="stat-box"><div class="label">Total hutang pribadi</div><div class="value">{{ rupiah($ringkasanData['totalHutangPri']) }}</div></div>
            <div class="stat-box"><div class="label">Pengurangan saldo (A&minus;B)</div><div class="value">{{ number_format($ringkasanData['totalSaldo'], 0, ',', '.') }}</div></div>
        </div>
        <div class="stat-box highlight" style="margin-top:14px;">
            <div class="label">Total keseluruhan aset</div>
            <div class="value">{{ rupiah($ringkasanData['grand']) }}</div>
        </div>
        <p class="note">Rumus mengikuti catatan: total stok minyak + total manajemen stok barang + total hutang pelanggan + total sisa barang. Hutang pribadi dan pengurangan saldo ditampilkan terpisah sebagai informasi tambahan.</p>
    </div>

    <div class="card">
        <h2>Kelola data</h2>
        <form method="POST" action="{{ route('pos.resetMonth') }}" style="display:inline;" onsubmit="return confirm('Hapus semua data bulan {{ $monthLabel }}? Tindakan ini tidak bisa dibatalkan.')">
            @csrf
            <input type="hidden" name="bulan" value="{{ $activeMonth }}">
            <button type="submit" class="ghost danger">Hapus data bulan ini saja</button>
        </form>
        <form method="POST" action="{{ route('pos.resetAll') }}" style="display:inline;" onsubmit="return confirm('Yakin hapus SEMUA data POS (semua bulan)? Tindakan ini tidak bisa dibatalkan.')" style="margin-left:8px;">
            @csrf
            <button type="submit" class="ghost danger" style="margin-left:8px;">Hapus SEMUA data (semua bulan)</button>
        </form>
    </div>
@endif
