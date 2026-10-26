<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Models\CatatanInput;
use App\Models\ItemPenjualan;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tab Transaksi: simpan/edit transaksi, pembayaran (modal & lunas),
 * toggle bayar kemarin, hapus, serta pengeluaran kas.
 * Transaksi TIDAK menulis customer_ledgers — rekap hutang di tab Hasil
 * hanya berisi catatan manual & riwayat lama.
 */
class TransaksiController extends Controller
{
    use BantuanPos;

    public function simpanTransaksi(Request $request)
    {
        $request->validate([
            'txName' => 'required|string|max:255',
            'txDate' => 'required|date',
        ], ['txName.required' => 'Isi nama pelanggan dulu.']);

        $inputJumlah = $request->input('qty', []);
        if (! is_array($inputJumlah)) {
            $inputJumlah = [];
        }
        $daftarItem = [];
        foreach ($inputJumlah as $productId => $jumlah) {
            $jumlah = static::uraiDesimal($jumlah);
            if ($jumlah !== null && $jumlah > 0) {
                $produk = Produk::find($productId);
                if ($produk) {
                    $daftarItem[] = ['product' => $produk, 'qty' => $jumlah];
                }
            }
        }

        // ID transaksi yang diedit harus masih ada sebelum transaksi disimpan.
        // Tanpa pemeriksaan ini, pengguna bisa menerima pesan "berhasil" padahal
        // tidak ada data yang tersimpan.
        $idDiedit = $request->input('editing_sale_id');
        if ($idDiedit && ! Penjualan::whereKey($idDiedit)->exists()) {
            return back()->withErrors([
                'editing_sale_id' => 'Transaksi yang diedit sudah tidak ditemukan (mungkin sudah dihapus). Muat ulang halaman lalu coba lagi.',
            ]);
        }

        $totalKotor = (int) round(array_sum(array_map(fn ($i) => $i['qty'] * $i['product']->harga, $daftarItem)));
        $totalDibulatkan = static::bulatkanTotal($totalKotor);
        $dibayarDisentuh = $request->input('tx_paid_touched', 0);
        $nilaiDibayar = $request->input('tx_paid');
        $dibayar = $dibayarDisentuh ? uraiRupiah($nilaiDibayar) : 0;

        // tanpa produk diizinkan asalkan kolom Dibayar diisi (kasus: hari ini hanya bayar hutang)
        if (empty($daftarItem) && $dibayar <= 0) {
            return back()->withErrors([
                'txQty' => 'Isi jumlah minimal satu produk atau isi kolom Dibayar (untuk pembayaran hutang tanpa transaksi).',
            ])->withInput();
        }

        $selisih = $totalDibulatkan - $dibayar;
        $tanggal = $request->input('txDate');
        $nama = trim((string) ($request->input('txName') ?? ''));
        // middleware ConvertEmptyStringsToNull mengubah input kosong menjadi null
        $catatan = (string) ($request->input('tx_note') ?? '');

        $idPenjualan = 0;
        DB::transaction(function () use (&$idPenjualan, $daftarItem, $totalKotor, $totalDibulatkan, $dibayar, $selisih, $tanggal, $nama, $catatan, $request) {
            $dibayarKemarin = 0;
            $idDiedit = $request->input('editing_sale_id');
            if ($idDiedit) {
                $penjualan = Penjualan::find($idDiedit);
                if (! $penjualan) {
                    return;
                }
                $dibayarKemarin = (int) $penjualan->dibayar_kemarin;
                $penjualan->update([
                    'tanggal' => $tanggal, 'nama' => $nama, 'total_kotor' => $totalKotor,
                    'total_dibulatkan' => $totalDibulatkan, 'dibayar' => $dibayar, 'selisih' => $selisih,
                    'catatan' => $catatan ?: null,
                    // edit tidak menurunkan status abu yang sudah di-set lewat modal Bayar
                    'tombol_bayar_diklik' => $penjualan->tombol_bayar_diklik || $dibayar > 0 || $dibayarKemarin > 0,
                ]);
                ItemPenjualan::where('penjualan_id', $penjualan->id)->delete();
            } else {
                $penjualan = Penjualan::create([
                    'tanggal' => $tanggal, 'nama' => $nama, 'total_kotor' => $totalKotor,
                    'total_dibulatkan' => $totalDibulatkan, 'dibayar' => $dibayar, 'selisih' => $selisih,
                    'catatan' => $catatan ?: null, 'tombol_bayar_diklik' => $dibayar > 0,
                ]);
            }

            $idPenjualan = $penjualan->id;

            foreach ($daftarItem as $itemPenjualan) {
                ItemPenjualan::create([
                    'penjualan_id' => $penjualan->id,
                    'produk_id' => $itemPenjualan['product']->id,
                    'nama' => $itemPenjualan['product']->nama,
                    'jumlah' => $itemPenjualan['qty'],
                    'harga' => $itemPenjualan['product']->harga,
                ]);
            }
            // (aturan "transaksi tidak menulis customer_ledgers" ada di docblock kelas)
        });

        $this->catatInput($request, $idPenjualan, $nama, $catatan, $daftarItem, $totalDibulatkan, $dibayar);

        // sukses: tutup form Transaksi Baru (buang query new/editing dari URL tujuan)
        return $this->alihkanKePos($request, [
            'tab' => 'transaksi', 'tx_date' => $tanggal, 'new' => null, 'editing' => null,
        ], 'Transaksi berhasil disimpan.');
    }

    /**
     * Bayar lunas satu transaksi (set paid = total, diff = 0, ditandai selesai).
     * Tidak punya route sendiri — dipanggil internal oleh bayarLewatModal saat 'lunas'.
     */
    public function bayarLunas(Request $request, int $idPenjualan)
    {
        $penjualan = Penjualan::find($idPenjualan);
        if (! $penjualan) {
            return back()->with('error', 'Transaksi tidak ditemukan.');
        }
        if ($penjualan->total_dibulatkan <= 0) {
            return back()->with('error', 'Transaksi ini tanpa tagihan — gunakan form Bayar.');
        }

        DB::transaction(function () use ($penjualan) {
            $penjualan->update([
                'dibayar' => $penjualan->total_dibulatkan,
                'selisih' => 0,
                'tombol_bayar_diklik' => true,
            ]);
            // (aturan "pembayaran tidak menulis customer_ledgers" ada di docblock kelas)
        });

        return $this->alihkanKePos($request, ['tab' => 'transaksi'], 'Pembayaran lunas.');
    }

    /**
     * Pembayaran lewat modal — keduanya bersifat "input terakhir menang" (SET, bukan akumulasi):
     * - "bayar hari ini" = TOTAL yang sudah dibayar transaksi ini (paid).
     * - "bayar kemarin" = nilai paid_kemarin transaksi ini.
     *   Ceklis ON → paid_kemarin = nilai form. Ceklis OFF → paid_kemarin = 0 (dinonaktifkan);
     *   client modal sudah menambahkan uang kemarin ke form "bayar hari ini" saat OFF,
     *   jadi uangnya pindah alokasi, tidak hilang (Dibayar & Diterima tetap).
     * Isian 0 diperbolehkan (mungkin memang belum bayar sama sekali).
     */
    public function bayarLewatModal(Request $request, int $idPenjualan)
    {
        $penjualan = Penjualan::find($idPenjualan);
        if (! $penjualan) {
            return back()->with('error', 'Transaksi tidak ditemukan.');
        }

        if ($request->input('lunas')) {
            return $this->bayarLunas($request, $idPenjualan);
        }

        $parseAmount = fn ($kunci) => uraiRupiah($request->input($kunci, '0'));
        $pakaiKemarin = (bool) $request->input('pakai_kemarin');
        $payKemarin = $pakaiKemarin ? $parseAmount('bayar_kemarin') : 0;
        $payHariIni = $parseAmount('bayar_hari_ini');

        DB::transaction(function () use ($penjualan, $pakaiKemarin, $payKemarin, $payHariIni) {
            $penjualan->update([
                'dibayar' => $payHariIni,
                'selisih' => $penjualan->total_dibulatkan - $payHariIni,
                'dibayar_kemarin' => $pakaiKemarin ? $payKemarin : 0,
                'kemarin_aktif' => $pakaiKemarin,
                // tombol Bayar dipencet = ditandai selesai (abu), termasuk nominal 0/kosong:
                // anggapannya transaksi selesai & hari ini memang tidak bayar sama sekali
                'tombol_bayar_diklik' => true,
            ]);
        });

        return $this->alihkanKePos($request, ['tab' => 'transaksi'], 'Pembayaran tercatat.');
    }

    public function muatUntukPembayaran(Request $request, int $idPenjualan)
    {
        $penjualan = Penjualan::with('daftarItem')->find($idPenjualan);
        if (! $penjualan) {
            return back();
        }

        $parameter = $this->buatParameterQuery($request, [
            'tab' => 'transaksi',
            'editing' => $idPenjualan,
            'tx_date' => Carbon::parse($penjualan->tanggal)->format('Y-m-d'),
            'tx_name' => $penjualan->nama,
            'tx_note' => $penjualan->catatan ?? '',
            // transaksi tanpa tagihan (bayar hutang): pertahankan nilai paid lama supaya tetap bisa disimpan ulang;
            // untuk tagihan: isi dengan paid sekarang supaya edit menampilkan angka asli (input terakhir menang)
            'tx_paid' => $penjualan->dibayar,
            'tx_paid_touched' => 1,
        ]);

        foreach ($penjualan->daftarItem as $itemPenjualan) {
            $parameter['qty_'.$itemPenjualan->produk_id] = (float) $itemPenjualan->jumlah;
        }

        return redirect()->route('pos', $parameter);
    }

    /**
     * Toggle ceklis "Bayar kemarin" di baris tabel (live, via fetch).
     * Hanya mengubah flag kemarin_active + menghitung ulang diff — TANPA memindahkan
     * uang antara paid dan paid_kemarin. diff dihitung:
     *   ON  → diff = rounded_total - paid (paid_kemarin dialokasikan ke "kemarin")
     *   OFF → diff = rounded_total - paid - paid_kemarin (uang ikut mengurangi tagihan)
     * Tidak menyentuh customer_ledgers & is_paid_btn_clicked.
     */
    public function alihkanKemarin(Request $request, int $idPenjualan)
    {
        $penjualan = Penjualan::find($idPenjualan);
        if (! $penjualan) {
            return response()->json(['ok' => false, 'error' => 'Transaksi tidak ditemukan.'], 404);
        }

        $aktif = (bool) $request->input('active');

        DB::transaction(function () use ($penjualan, $aktif) {
            $penjualan->kemarin_aktif = $aktif;
            $penjualan->selisih = $penjualan->total_dibulatkan - $penjualan->dibayar - ($aktif ? 0 : $penjualan->dibayar_kemarin);
            $penjualan->save();
        });

        return response()->json([
            'ok' => true,
            'paid' => (int) $penjualan->dibayar,
            'paid_kemarin' => (int) $penjualan->dibayar_kemarin,
            'diff' => (int) $penjualan->selisih,
        ]);
    }

    public function hapusTransaksi(Request $request, int $idPenjualan)
    {
        Penjualan::where('id', $idPenjualan)->delete();
        $parameter = $this->buatParameterQuery($request, ['tab' => 'transaksi']);
        unset($parameter['editing']);

        return redirect()->route('pos', $parameter)->with('success', 'Transaksi dihapus.');
    }

    public function simpanPengeluaran(Request $request)
    {
        $jumlah = uraiRupiah($request->input('expAmount')); // aman terima "15.000" maupun "15000"
        if ($jumlah <= 0) {
            return back()->withErrors(['expAmount' => 'Isi jumlah uang yang diambil.'])->withInput();
        }
        Pengeluaran::create([
            'tanggal' => $request->input('expDate') ?: now()->toDateString(),
            'jumlah' => $jumlah,
            'catatan' => $request->input('expNote') ?: null,
        ]);

        return $this->alihkanKePos($request, [
            'tab' => 'transaksi', 'exp_date' => $request->input('expDate') ?: now()->toDateString(),
        ], 'Pengeluaran dicatat.');
    }

    public function hapusPengeluaran(Request $request, int $id)
    {
        Pengeluaran::where('id', $id)->delete();

        return back()->with('success', 'Pengeluaran dihapus.');
    }

    /**
     * Catat metode input (speech/manual) + hasil parser untuk analisis akurasi & efisiensi.
     */
    private function catatInput(Request $request, int $idPenjualan, string $nama, ?string $catatan, array $daftarItem, int $totalDibulatkan, int $dibayar): void
    {
        $catatan = (string) ($catatan ?? '');
        $metode = $request->input('input_method');
        if (! $metode) {
            return;
        }

        try {
            $akhir = [
                'name' => $nama,
                'note' => $catatan ?: null,
                'qty' => [],
                'price' => [],
                'paid' => $dibayar,
                'total' => $totalDibulatkan,
            ];
            foreach ($daftarItem as $itemPenjualan) {
                $idProduk = (string) $itemPenjualan['product']->id;
                $akhir['qty'][$idProduk] = $itemPenjualan['qty'];
                $akhir['price'][$idProduk] = (int) $itemPenjualan['product']->harga;
            }

            $hasilUrai = null;
            $teksMentah = null;
            $teksNormal = null;
            $inputTerurai = $request->input('input_parsed');
            if ($inputTerurai) {
                $kandidat = json_decode($inputTerurai, true);
                if (is_array($kandidat)) {
                    $hasilUrai = $kandidat;
                    $teksMentah = $kandidat['raw'] ?? null;
                    $teksNormal = $kandidat['normalized'] ?? null;
                }
            }
            if (! $teksMentah) {
                $teksMentah = $request->input('input_text');
            }

            $durasi = $request->input('input_duration_ms');
            $mulaiPada = $request->input('input_started_at');

            CatatanInput::create([
                'metode' => (string) $metode,
                'tab' => 'transaksi',
                'perangkat' => mb_substr((string) $request->userAgent(), 0, 255),
                'teks_mentah' => $teksMentah,
                'teks_normal' => $teksNormal,
                'json_urai' => $hasilUrai,
                'json_akhir' => $akhir,
                'json_cocok' => $hasilUrai ? $this->bandingkanHasilUrai($hasilUrai, $akhir) : null,
                'durasi_ms' => is_numeric($durasi) ? (int) $durasi : null,
                'mulai_pada' => $mulaiPada ? Carbon::parse($mulaiPada) : null,
                'penjualan_id' => $idPenjualan ?: null,
                'status' => 'ok',
            ]);
        } catch (\Throwable $e) {
            report($e);
            try {
                CatatanInput::create([
                    'metode' => (string) $metode,
                    'tab' => 'transaksi',
                    'perangkat' => mb_substr((string) $request->userAgent(), 0, 255),
                    'teks_mentah' => $request->input('input_text'),
                    'status' => 'error',
                    'kesalahan' => mb_substr($e->getMessage(), 0, 1000),
                ]);
            } catch (\Throwable $ignored) {
                // logging tidak boleh menggagalkan transaksi
            }
        }
    }

    /** Bandingkan hasil parser dengan nilai akhir form (ground truth). */
    private function bandingkanHasilUrai(array $hasilUrai, array $akhir): array
    {
        $cocokNama = $this->teksSama($hasilUrai['name'] ?? null, $akhir['name']);

        $cocokJumlah = [];
        $jumlahTerurai = [];
        $hargaTerurai = [];
        foreach (($hasilUrai['items'] ?? []) as $itemPenjualan) {
            $idProduk = (string) ($itemPenjualan['product_id'] ?? '');
            if ($idProduk === '') {
                continue;
            }
            $jumlahTerurai[$idProduk] = ($jumlahTerurai[$idProduk] ?? 0) + (float) ($itemPenjualan['qty'] ?? 0);
            $hargaTerurai[$idProduk] = (int) ($itemPenjualan['price'] ?? 0);
        }
        $semuaIdProduk = array_unique(array_merge(array_keys($jumlahTerurai), array_keys($akhir['qty'])));
        foreach ($semuaIdProduk as $idProduk) {
            $nilaiTerurai = round($jumlahTerurai[$idProduk] ?? 0, 3);
            $nilaiAkhir = round($akhir['qty'][$idProduk] ?? 0, 3);
            $cocokJumlah[$idProduk] = ['parsed' => $nilaiTerurai, 'final' => $nilaiAkhir, 'match' => $nilaiTerurai === $nilaiAkhir];
        }

        $cocokHarga = [];
        foreach ($semuaIdProduk as $idProduk) {
            $nilaiTerurai = (int) ($hargaTerurai[$idProduk] ?? 0);
            $nilaiAkhir = (int) ($akhir['price'][$idProduk] ?? 0);
            $cocokHarga[$idProduk] = ['parsed' => $nilaiTerurai, 'final' => $nilaiAkhir, 'match' => $nilaiTerurai === $nilaiAkhir];
        }

        $catatanTerurai = ($hasilUrai['note'] ?? null) ?: null;
        $catatanAkhir = $akhir['note'];
        $cocokCatatan = $this->teksSama($catatanTerurai, $catatanAkhir);

        $itemBenar = array_filter($cocokJumlah, fn ($m) => $m['match']);
        $akurasiItem = count($semuaIdProduk) > 0 ? count($itemBenar) / count($semuaIdProduk) : ($cocokNama ? 1.0 : 0.0);
        $hargaBenar = array_filter($cocokHarga, fn ($m) => $m['match']);
        $akurasiHarga = count($semuaIdProduk) > 0 ? count($hargaBenar) / count($semuaIdProduk) : ($cocokNama ? 1.0 : 0.0);

        return [
            'name_match' => $cocokNama,
            'note_match' => $cocokCatatan,
            'qty' => $cocokJumlah,
            'price' => $cocokHarga,
            'item_accuracy' => round($akurasiItem, 4),
            'price_accuracy' => round($akurasiHarga, 4),
            'overall' => $cocokNama && $cocokCatatan && $akurasiItem >= 1.0 && $akurasiHarga >= 1.0,
        ];
    }

    /** Bandingkan dua teks setelah dinormalisasi (huruf kecil + spasi rapat) — dipakai untuk nama & catatan. */
    private function teksSama(?string $a, ?string $b): bool
    {
        return $this->normalisasiTeks($a) === $this->normalisasiTeks($b);
    }

    private function normalisasiTeks(?string $nilai): string
    {
        $nilai = mb_strtolower(trim((string) $nilai));
        $nilai = preg_replace('/\s+/u', ' ', $nilai);

        return (string) $nilai;
    }
}
