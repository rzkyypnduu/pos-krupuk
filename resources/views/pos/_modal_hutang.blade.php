{{-- Modal tambah hutang pelanggan — tampil saat $modalHutangTerbuka (dibuka dari tab Hasil);
     POST → HutangPelangganController::simpanHutangPelanggan. Bootstrap 5 modal;
     saat ditutup (Esc/backdrop), URL dibersihkan dari param debt_modal agar bisa dibuka lagi. --}}
@if($modalHutangTerbuka)
    @php
        $urlTutupHutang = route('pos', ['bulan' => $bulanAktif, 'tx_date' => $tanggalTransaksi, 'tab' => 'hasil']);
    @endphp
    <div class="modal fade" id="debtModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-debt-close-url="{{ $urlTutupHutang }}">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('pos.simpanHutangPelanggan', ['bulan' => $bulanAktif, 'tx_date' => $tanggalTransaksi, 'tab' => 'hasil']) }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title fs-5">{{ $namaModalHutang ? 'Tambah hutang — ' . $namaModalHutang : 'Tambah hutang pelanggan' }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="field">
                        <label for="debtModalName">Nama pelanggan</label>
                        <input id="debtModalName" type="text" name="debtModalName" value="{{ $namaModalHutang }}" required placeholder="Contoh: Mut">
                        @error('debtModalName') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="debtModalAmount">Jumlah hutang (Rp)</label>
                        <input id="debtModalAmount" type="text" inputmode="numeric" name="debtModalAmount" data-money min="0" required placeholder="0">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan hutang</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('debtModal');
        if (!el || !window.bootstrap) return;
        var modal = new bootstrap.Modal(el);
        el.addEventListener('hidden.bs.modal', function () {
            window.history.replaceState({}, '', el.getAttribute('data-debt-close-url'));
        });
        modal.show();
        var first = el.querySelector('input[name="debtModalName"], input[name="debtModalAmount"]');
        if (first) el.addEventListener('shown.bs.modal', function () { first.focus(); }, { once: true });
    });
    </script>
@endif
