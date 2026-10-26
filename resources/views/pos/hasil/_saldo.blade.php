{{-- 4. PENGURANGAN SALDO --}}
<div class="section-header">
    <h2>4. Pengurangan Saldo</h2>
</div>

@php
    $nilaiASaldo = old('saldoA', $saldoTerakhir?->nilai_a);
    $nilaiBSaldo = old('saldoB', $saldoTerakhir?->nilai_b);
    $teksASaldo = is_numeric($nilaiASaldo) ? number_format((float) $nilaiASaldo, 0, ',', '.') : (string) $nilaiASaldo;
    $teksBSaldo = is_numeric($nilaiBSaldo) ? number_format((float) $nilaiBSaldo, 0, ',', '.') : (string) $nilaiBSaldo;
@endphp

<div class="card">
    <h2>Pengurangan saldo</h2>
    <form method="POST" action="{{ route('pos.simpanSaldo', $parameterQuery) }}">
        @csrf
        <div class="field-row">
        <div class="field"><label for="saldoA">Angka A (Rp)</label><input type="text" inputmode="numeric" id="saldoA" name="saldoA" data-money value="{{ $teksASaldo }}">@error('saldoA') <div class="field-error">{{ $message }}</div> @enderror</div>
        <div class="field"><label for="saldoB">Angka B (Rp)</label><input type="text" inputmode="numeric" id="saldoB" name="saldoB" data-money value="{{ $teksBSaldo }}">@error('saldoB') <div class="field-error">{{ $message }}</div> @enderror</div>
            <div class="field" style="flex:0; align-self:flex-end;"><button type="submit" class="btn btn-primary">Simpan</button></div>
        </div>
    </form>
    <div class="summary-line total" style="margin-top:12px;">
        <span>Hasil pengurangan (A&minus;B)</span>
        <span class="val">{{ $saldoLogs->isNotEmpty() ? rupiah($saldoHari) : 'Belum ada' }}</span>
    </div>
    @if($saldoLogs->isNotEmpty())
        <form method="POST" action="{{ route('pos.hapusSaldo', $parameterQuery) }}" style="margin-top:8px;" data-confirm="Hapus hasil pengurangan saldo tanggal ini?">
            @csrf
            <button type="submit" class="btn btn-outline-danger">Hapus</button>
        </form>
    @endif
</div>
