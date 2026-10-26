{{-- 3. HUTANG PRIBADI --}}
<div class="section-header">
    <h2>3. Hutang Pribadi</h2>
</div>

<div class="card">
    <h2>Catat hutang pribadi</h2>
    <form method="POST" action="{{ route('pos.simpanHutangPribadi', $parameterQuery) }}">
        @csrf
        <div class="field-row">
            <div class="field"><label for="hprName">Nama</label><input type="text" id="hprName" name="hprName" value="{{ old('hprName') }}">
                @error('hprName') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field"><label for="hprAmount">Jumlah (Rp)</label><input type="text" inputmode="numeric" id="hprAmount" name="hprAmount" data-money min="0" value="{{ old('hprAmount') }}">
                @error('hprAmount') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
    </form>
</div>
<div class="card" id="hutangPribadiCard">
    <h2>Daftar hutang pribadi (hari ini)</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nama</th><th class="num">Jumlah</th><th></th></tr></thead>
            <tbody>
                @forelse ($barisHutangPribadi as $baris)
                    <tr data-hpr-row="{{ $baris['id'] }}">
                        <td><input type="text" class="cell-edit cell-name" data-hpr="name" data-val="{{ $baris['name'] }}" value="{{ $baris['name'] }}" placeholder="Nama"></td>
                        <td class="num"><input type="text" inputmode="numeric" min="0" class="sack-cell" data-hpr="amount" data-money data-val="{{ number_format($baris['amount'], 0, ',', '.') }}" value="{{ number_format($baris['amount'], 0, ',', '.') }}" placeholder="0"></td>
                        <td>
                            <form method="POST" action="{{ route('pos.hapusPersonalLedger', $baris['id']) }}" style="display:inline;" data-confirm="Hapus hutang pribadi ini?">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada catatan hutang pribadi hari ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="summary-line total"><span>Total hutang pribadi hari ini</span><span class="val" id="hprGrand">{{ rupiah($totalHutangPribadi) }}</span></div>
</div>
