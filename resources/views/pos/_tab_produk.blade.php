{{-- Bagian 1: tambah produk & data contoh (POST → ProdukController::simpanProduk/seedProduk) --}}
<div class="card">
    <h2>Tambah produk</h2>
    <form method="POST" action="{{ route('pos.simpanProduk') }}">
        @csrf
        <div class="field-row">
            <div class="field">
                <label for="prodName">Nama produk</label>
                <input type="text" id="prodName" name="prodName" placeholder="Contoh: Kabur">
                @error('prodName') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="prodPrice">Harga per kg (Rp)</label>
                <input type="text" inputmode="numeric" id="prodPrice" name="prodPrice" data-money min="0" placeholder="0">
            </div>
        </div>
        <div class="btn-inline">
            <button type="submit" class="btn btn-primary">Tambah produk</button>
        </div>
    </form>
    <form method="POST" action="{{ route('pos.seedProduk') }}" class="btn-inline-form">
        @csrf
        <button type="submit" class="btn btn-outline-secondary">Isi contoh nama produk</button>
    </form>
</div>

{{-- Bagian 2: daftar harga — ubah harga inline & hapus --}}
<div class="card">
    <h2>Daftar produk</h2>
    <div class="table-wrap">
        <table class="prod-table">
            <thead><tr><th class="center">Nama</th><th class="center">Harga / kg</th><th></th></tr></thead>
            <tbody>
                @forelse ($daftarProduk as $produk)
                    <tr>
                        <td class="center">{{ $produk->nama }}</td>
                        <td class="center">{!! $produk->harga ? rupiah($produk->harga) : '<span class="warn-price">belum diatur</span>' !!}</td>
                        <td>
                            <div class="row-actions">
                                <div data-edit-view="{{ $produk->id }}">
                                    <button type="button" class="btn btn-outline-secondary" data-edit-show="{{ $produk->id }}">Ubah harga</button>
                                </div>
                                <div data-edit-form="{{ $produk->id }}" class="{{ $errors->has('price') ? '' : 'd-none' }}" style="display:flex;gap:6px;align-items:center;">
                                    <form method="POST" action="{{ route('pos.ubahHarga', $produk->id) }}" style="display:flex;gap:6px;align-items:center;">
                                        @csrf
                                        <input type="text" inputmode="numeric" name="price" data-money value="{{ old('price', $produk->harga) }}" min="0" style="width:130px;padding:8px 10px;font-size:15px;">@error('price') <div class="field-error">{{ $message }}</div> @enderror
                                        <button type="submit" class="btn btn-primary" style="padding:8px 14px;font-size:14px;">OK</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-secondary" data-edit-hide="{{ $produk->id }}" style="padding:8px 14px;font-size:14px;">Batal</button>
                                </div>
                                <form method="POST" action="{{ route('pos.hapusProduk', $produk->id) }}" style="display:inline;" data-confirm="Hapus produk {{ $produk->nama }}?">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada produk. Tambahkan produk atau pakai tombol contoh di atas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
document.addEventListener('click', function (e) {
    var show = e.target.closest('[data-edit-show]');
    if (show) {
        var id = show.getAttribute('data-edit-show');
        var view = document.querySelector('[data-edit-view="' + id + '"]');
        var form = document.querySelector('[data-edit-form="' + id + '"]');
        if (view) view.classList.add('d-none');
        if (form) {
            form.classList.remove('d-none');
            var inp = form.querySelector('input[name="price"]');
            if (inp) inp.focus();
        }
        return;
    }
    var hide = e.target.closest('[data-edit-hide]');
    if (hide) {
        var id2 = hide.getAttribute('data-edit-hide');
        var view2 = document.querySelector('[data-edit-view="' + id2 + '"]');
        var form2 = document.querySelector('[data-edit-form="' + id2 + '"]');
        if (view2) view2.classList.remove('d-none');
        if (form2) form2.classList.add('d-none');
    }
});
</script>
