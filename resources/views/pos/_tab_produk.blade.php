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
                <input type="number" id="prodPrice" name="prodPrice" min="0" step="500">
            </div>
        </div>
        <button type="submit" class="primary">Tambah produk</button>
    </form>
    <form method="POST" action="{{ route('pos.seedProduk') }}" style="display:inline; margin-top:8px;">
        @csrf
        <button type="submit" class="ghost" style="margin-left:8px;">Isi contoh nama produk dari catatan</button>
    </form>
</div>

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
                        <td x-data="{ editing: {{ $errors->has('price') ? 'true' : 'false' }} }">
                            <div class="row-actions">
                            <div x-show="!editing">
                                <button type="button" class="ghost" @click="editing = true">Ubah harga</button>
                            </div>
                            <div x-show="editing" style="display:flex;gap:6px;align-items:center;">
                                <form method="POST" action="{{ route('pos.ubahHarga', $p->id) }}" style="display:flex;gap:6px;align-items:center;">
                                    @csrf
                                    <input type="number" name="price" value="{{ old('price', $p->price) }}" min="0" step="500" style="width:120px;padding:8px 10px;font-size:15px;">@error('price') <div class="field-error">{{ $message }}</div> @enderror
                                    <button type="submit" class="primary" style="padding:8px 14px;font-size:14px;">OK</button>
                                </form>
                                <button type="button" class="ghost" @click="editing = false" style="padding:8px 14px;font-size:14px;">Batal</button>
                            </div>
                            <form method="POST" action="{{ route('pos.hapusProduk', $p->id) }}" style="display:inline;" onsubmit="return confirm('Hapus produk {{ $p->name }}?')">
                                @csrf
                                <button type="submit" class="ghost danger">Hapus</button>
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
