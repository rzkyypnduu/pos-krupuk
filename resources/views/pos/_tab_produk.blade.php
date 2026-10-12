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
        <button type="submit" class="btn btn-primary">Tambah produk</button>
    </form>
    <form method="POST" action="{{ route('pos.seedProduk') }}" style="display:inline; margin-top:8px;">
        @csrf
        <button type="submit" class="btn btn-outline-secondary" style="margin-left:8px;">Isi contoh nama produk dari catatan</button>
    </form>
</div>

{{-- Bagian 2: daftar harga — ubah harga inline & hapus --}}
<div class="card">
    <h2>Daftar produk</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nama</th><th class="num">Harga / kg</th><th></th></tr></thead>
            <tbody>
                @forelse ($products as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td class="num">{!! $p->price ? rupiah($p->price) : '<span class="warn-price">belum diatur</span>' !!}</td>
                        <td>
                            <div class="row-actions">
                                <div data-edit-view="{{ $p->id }}">
                                    <button type="button" class="btn btn-outline-secondary" data-edit-show="{{ $p->id }}">Ubah harga</button>
                                </div>
                                <div data-edit-form="{{ $p->id }}" class="{{ $errors->has('price') ? '' : 'd-none' }}" style="display:flex;gap:6px;align-items:center;">
                                    <form method="POST" action="{{ route('pos.ubahHarga', $p->id) }}" style="display:flex;gap:6px;align-items:center;">
                                        @csrf
                                        <input type="text" inputmode="numeric" name="price" data-money value="{{ old('price', $p->price) }}" min="0" style="width:130px;padding:8px 10px;font-size:15px;">@error('price') <div class="field-error">{{ $message }}</div> @enderror
                                        <button type="submit" class="btn btn-primary" style="padding:8px 14px;font-size:14px;">OK</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-secondary" data-edit-hide="{{ $p->id }}" style="padding:8px 14px;font-size:14px;">Batal</button>
                                </div>
                                <form method="POST" action="{{ route('pos.hapusProduk', $p->id) }}" style="display:inline;" data-confirm="Hapus produk {{ $p->name }}?">
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
