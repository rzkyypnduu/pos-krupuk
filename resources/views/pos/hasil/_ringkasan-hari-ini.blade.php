{{-- RINGKASAN HARI INI — baris, tanpa card, paling bawah, lebar penuh (rata kanan kiri) --}}
<div class="section-header" style="margin-top:24px;">
    <h2>Ringkasan Hari Ini</h2>
</div>

<div>
    <div class="summary-line"><span>Stok minyak</span><span class="val">{{ rupiah($daftarStokMinyak->sum(fn ($o) => $o->jumlah * $o->harga)) }}</span></div>
    <div class="summary-line"><span>Manajemen stok barang</span><span class="val">{{ rupiah($daftarManajemenStok->sum(fn ($o) => $o->subtotal())) }}</span></div>
    <div class="summary-line"><span>Sisa barang</span><span class="val">{{ rupiah($daftarSisaStok->sum(fn ($o) => $o->jumlah * $o->harga)) }}</span></div>
    <div class="summary-line"><span>Hutang pelanggan (piutang toko)</span><span class="val">{{ rupiah($totalHutangPelanggan) }}</span></div>
    <div class="summary-line"><span>Hutang pribadi</span><span class="val">{{ rupiah($hutangPribadiHari) }}</span></div>
    <div class="summary-line"><span>Pengurangan saldo</span><span class="val">{{ number_format($saldoHari, 0, ',', '.') }}</span></div>
    <div class="summary-line total" title="Stok minyak + total hutang pelanggan + stok barang + sisa barang">
        <span>Total aset hari ini</span>
        <span class="val">{{ rupiah($asetHari) }}</span>
    </div>
    <div class="summary-line total" title="Total aset hari ini &minus; total hutang pribadi">
        <span>Saldo (Total aset - Hutang pribadi)</span>
        <span class="val">{{ rupiah($asetHari - $hutangPribadiHari) }}</span>
    </div>
    <div class="summary-line total" title="Total aset hari ini &minus; pengurangan saldo &minus; hutang pribadi">
        <span>Total (Total aset - Pengurangan saldo)</span>
        <span class="val">{{ rupiah($asetHari - $saldoHari - $hutangPribadiHari) }}</span>
    </div>
</div>
