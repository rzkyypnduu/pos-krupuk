@if($debtModalOpen)
    <div class="modal-backdrop open" id="debtModal" onclick="window.location.href='{{ route('pos', ['bulan' => $activeMonth, 'tx_date' => $txDate, 'tab' => 'hasil']) }}'">
        <form class="debt-modal" method="POST" action="{{ route('pos.simpanHutangPelanggan', ['bulan' => $activeMonth, 'tx_date' => $txDate, 'tab' => 'hasil']) }}" onclick="event.stopPropagation()">
            @csrf
            <div class="debt-modal-head">
                <h2>{{ $debtModalName ? 'Tambah hutang — ' . $debtModalName : 'Tambah hutang pelanggan' }}</h2>
                <p>Catat hutang baru akan ditambahkan ke rekap.</p>
            </div>
            <div class="debt-modal-body">
                <div class="field">
                    <label for="debtModalName">Nama pelanggan</label>
                    <input id="debtModalName" type="text" name="debtModalName" value="{{ $debtModalName }}" required placeholder="Contoh: Mut">
                    @error('debtModalName') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="debtModalAmount">Jumlah hutang (Rp)</label>
                    <input id="debtModalAmount" type="number" name="debtModalAmount" min="0" step="500" required placeholder="0">
                    @error('debtModalAmount') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="debtModalDate">Tanggal</label>
                    <input id="debtModalDate" type="date" name="debtModalDate" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="field">
                    <label for="debtModalNote">Catatan (opsional)</label>
                    <input id="debtModalNote" type="text" name="debtModalNote" placeholder="Contoh: Hutang belanja">
                </div>
                <div class="modal-actions">
                    <a href="{{ route('pos', ['bulan' => $activeMonth, 'tx_date' => $txDate, 'tab' => 'hasil']) }}" class="ghost" style="text-decoration:none;">Batal</a>
                    <button type="submit" class="primary">Simpan hutang</button>
                </div>
            </div>
        </form>
    </div>
@endif
