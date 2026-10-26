<?php

namespace App\Services;

use App\Http\Controllers\Concerns\BantuanPos;
use App\Models\BukuPelanggan;
use App\Models\BukuPribadi;
use App\Models\ItemPenjualan;
use App\Models\ManajemenStok;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\PotonganSaldo;
use App\Models\Produk;
use App\Models\SisaStok;
use App\Models\StokMinyak;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Menyiapkan seluruh data yang dibutuhkan halaman POS (satu rute GET /pos).
 * Controller hanya memilih view; query dan perhitungan ada di sini agar Blade
 * tidak berisi logika bisnis.
 */
class ServiceDataHalamanPos
{
    use BantuanPos;

    public function __construct(
        private ServiceHutang $hutang,
        private ServiceLembarHasil $lembarHasil,
    ) {}

    /**
     * @return array<string, mixed> Data yang siap dikirim ke view pos.index.
     */
    public function untukHalaman(Request $request): array
    {
        $tabAktif = $request->query('tab', 'transaksi');
        $idPenjualanDiedit = $request->query('editing', null);
        $namaDetailHutangPelanggan = $request->query('hp_detail', '');
        $modalHutangTerbuka = $request->query('debt_modal', 0);
        $namaModalHutang = $request->query('debt_name', '');
        $tampilkanFormTransaksi = $request->has('new') || (bool) $idPenjualanDiedit;

        $daftarProduk = Produk::orderBy('nama')->get();

        // Satu tanggal aktif tunggal untuk semua tab; bulan aktif = bulan dari tanggal itu.
        $inputTanggalTransaksi = old('txDate', $request->query('tx_date'));
        $parameterBulan = (string) $request->query('bulan', '');
        if (is_string($inputTanggalTransaksi) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $inputTanggalTransaksi) && $this->tanggalValid($inputTanggalTransaksi)) {
            $tanggalTransaksi = $inputTanggalTransaksi;
            $bulanAktif = substr($tanggalTransaksi, 0, 7);
        } elseif (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $parameterBulan)) {
            // URL lama ?bulan=... tanpa tanggal: buka tanggal 1 bulan itu.
            $bulanAktif = $parameterBulan;
            $tanggalTransaksi = $bulanAktif.'-01';
        } else {
            $tanggalTransaksi = now()->toDateString();
            $bulanAktif = substr($tanggalTransaksi, 0, 7);
        }

        $namaTransaksi = (string) old('txName', $request->query('tx_name', ''));
        $jumlahLama = old('qty');
        $jumlahTransaksi = [];
        foreach ($daftarProduk as $produk) {
            $jumlah = is_array($jumlahLama) && array_key_exists($produk->id, $jumlahLama)
                ? $jumlahLama[$produk->id]
                : $request->query('qty_'.$produk->id, null);
            $jumlahTransaksi[$produk->id] = static::uraiDesimal($jumlah);
        }
        $dibayarTransaksi = old('tx_paid', $request->query('tx_paid', null));
        $dibayarTransaksiDisentuh = old('tx_paid_touched', $request->query('tx_paid_touched', 0));
        $catatanTransaksi = (string) old('tx_note', $request->query('tx_note', ''));

        $tanggalPengeluaran = $request->query('exp_date', now()->toDateString());
        $namaManajemenStok = $request->query('sm_name', '');
        $hargaManajemenStok = $request->query('sm_price', null);
        $namaSisaStok = (string) old('remainName', '');
        $jumlahSisaStok = old('remainQty', '');
        $hargaSisaStok = old('remainPrice', 0);

        // Tab Hasil = lembar harian. Saat pertama kali membuka tanggal yang masih
        // kosong, isi tab hasil disalin dari tanggal terakhir yang dilihat lalu
        // kedua tanggal berjalan independen.
        if ($tabAktif === 'hasil') {
            $this->lembarHasil->pastikanLembar($tanggalTransaksi);
        }
        session(['hasil_last_date' => $tanggalTransaksi]);

        $daftarPenjualan = Penjualan::where('tanggal', $tanggalTransaksi)->with('daftarItem')->orderBy('id')->get();
        $daftarPengeluaranHari = Pengeluaran::where('tanggal', $tanggalPengeluaran ?: now()->toDateString())->orderBy('id')->get();

        $daftarStokMinyak = StokMinyak::where('tanggal', $tanggalTransaksi)->orderBy('id')->get();
        $daftarManajemenStok = ManajemenStok::where(fn ($q) => $q->where('tanggal', $tanggalTransaksi)->orWhereNull('tanggal'))->orderBy('id')->get();
        $daftarSisaStok = SisaStok::where('tanggal', $tanggalTransaksi)->orderBy('id')->get();
        $personalLedgers = BukuPribadi::where('tanggal', $tanggalTransaksi)->orderBy('id')->get();
        $saldoLogs = PotonganSaldo::where('tanggal', $tanggalTransaksi)->orderBy('id')->get();

        $daftarNamaHutangPelanggan = $this->bukuPelangganLingkup($tanggalTransaksi)->select('nama')->distinct()->orderBy('nama')->pluck('nama')
            ->filter(fn ($nama) => $this->hutang->prosesHutangPelanggan($nama, $tanggalTransaksi)['totalSisa'] > 0)->values()->all();
        $semuaNamaPelanggan = Penjualan::pluck('nama')->merge(BukuPelanggan::pluck('nama'))->unique()->sort()->values()->all();
        $namaPemegangManajemenStok = ManajemenStok::pluck('nama')->unique()->sort()->values()->all();

        $hutangPelangganDiproses = [];
        $maxCols = 1;
        $totalHutangPelanggan = 0;
        foreach ($daftarNamaHutangPelanggan as $nama) {
            $dataHutang = $this->hutang->prosesHutangPelanggan($nama, $tanggalTransaksi);
            $hutangPelangganDiproses[$nama] = $dataHutang;
            if (count($dataHutang['activeDebts']) > $maxCols) {
                $maxCols = count($dataHutang['activeDebts']);
            }
            $totalHutangPelanggan += $dataHutang['totalSisa'];
        }

        $entriHutangPelanggan = null;
        if ($namaDetailHutangPelanggan) {
            $entriHutangPelanggan = $this->hutang->entriDetailHutangPelanggan($namaDetailHutangPelanggan, $tanggalTransaksi);
        }

        $rekapPerProduk = [];
        foreach ($daftarPenjualan as $penjualan) {
            foreach ($penjualan->daftarItem as $itemPenjualan) {
                $rekapPerProduk[$itemPenjualan->nama] = ($rekapPerProduk[$itemPenjualan->nama] ?? 0) + (float) $itemPenjualan->jumlah;
            }
        }
        $diterimaHari = (int) $daftarPenjualan->sum('dibayar') + (int) $daftarPenjualan->sum('dibayar_kemarin');
        $pengeluaranHari = (int) $daftarPengeluaranHari->sum('jumlah');
        $totalRekap = [
            'kg' => (float) $daftarPenjualan->flatMap->daftarItem->sum('jumlah'),
            'dibayar' => $diterimaHari,
            'pengeluaran' => $pengeluaranHari,
            'kas_bersih' => $diterimaHari - $pengeluaranHari,
        ];

        // Ringkasan Bulan (tab Transaksi) tetap bulanan, dihitung terpisah dari
        // data harian tab Hasil.
        [$mulai, $selesai] = $this->rentangBulan($bulanAktif);
        $penjualanBulan = Penjualan::whereBetween('tanggal', [$mulai, $selesai])->with('daftarItem')->orderBy('tanggal')->orderBy('id')->get();
        $rekapBulanPerProduk = [];
        foreach ($penjualanBulan as $penjualan) {
            foreach ($penjualan->daftarItem as $itemPenjualan) {
                $rekapBulanPerProduk[$itemPenjualan->nama] = ($rekapBulanPerProduk[$itemPenjualan->nama] ?? 0) + (float) $itemPenjualan->jumlah;
            }
        }
        ksort($rekapBulanPerProduk);
        $daftarPengeluaranBulan = Pengeluaran::whereBetween('tanggal', [$mulai, $selesai])->get();
        $diterimaBulan = (int) $penjualanBulan->sum('dibayar') + (int) $penjualanBulan->sum('dibayar_kemarin');
        $pengeluaranBulan = (int) $daftarPengeluaranBulan->sum('jumlah');
        $totalRekapBulan = [
            'kg' => (float) $penjualanBulan->flatMap->daftarItem->sum('jumlah'),
            'dibayar' => $diterimaBulan,
            'pengeluaran' => $pengeluaranBulan,
            'kas_bersih' => $diterimaBulan - $pengeluaranBulan,
        ];

        $dataRingkasan = null;
        if ($tabAktif === 'ringkasan') {
            $dataRingkasan = $this->ringkasan($bulanAktif, $tanggalTransaksi, $totalHutangPelanggan);
        }

        $labelBulan = $this->labelBulan($bulanAktif);

        // Data siap tampil untuk tab Hasil.
        $parameterQuery = ['bulan' => $bulanAktif, 'tx_date' => $tanggalTransaksi, 'tab' => 'hasil'];
        $asetHari = $daftarStokMinyak->sum(fn ($o) => $o->jumlah * $o->harga)
            + $daftarManajemenStok->sum(fn ($o) => $o->subtotal())
            + $daftarSisaStok->sum(fn ($o) => $o->jumlah * $o->harga)
            + $totalHutangPelanggan;
        $hutangPribadiHari = (int) $personalLedgers->sum('jumlah');
        $saldoHari = (int) $saldoLogs->sum(fn ($l) => $l->hasil());
        $saldoTerakhir = $saldoLogs->last();
        $stokMinyakTersimpan = $daftarStokMinyak->first();
        $barisManajemenStok = $daftarManajemenStok->map(fn ($o) => [
            'id' => $o->id,
            'date' => $o->tanggal?->format('Y-m-d') ?? '-',
            'name' => $o->nama,
            'price' => $o->harga,
            'sacks' => collect($o->kelompok ?? [])
                ->flatMap(fn ($b) => $b['sacks'] ?? [])
                ->map(fn ($v) => is_numeric($v) ? (float) $v : 0)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all(),
            'totalQty' => round(ManajemenStok::totalJumlah($o->kelompok ?? []), 2),
            'subtotal' => $o->subtotal(),
        ])->values()->all();
        $barisSisaStok = $daftarSisaStok->map(fn ($o) => [
            'id' => $o->id,
            'date' => $o->tanggal?->format('Y-m-d') ?? '-',
            'name' => $o->nama,
            'qty' => $o->jumlah + 0,
            'price' => $o->harga,
        ])->values()->all();
        $barisHutangPribadi = $personalLedgers->map(fn ($l) => [
            'id' => $l->id,
            'date' => $l->tanggal->format('Y-m-d'),
            'name' => $l->nama,
            'amount' => $l->jumlah,
        ])->values()->all();
        $maksKarungManajemenStok = max([1, ...array_map(fn ($r) => count($r['sacks']), $barisManajemenStok)]);
        $totalManajemenStok = array_sum(array_column($barisManajemenStok, 'subtotal'));
        $totalSisaStok = array_sum(array_map(fn ($r) => $r['qty'] * $r['price'], $barisSisaStok));
        $totalHutangPribadi = array_sum(array_column($barisHutangPribadi, 'amount'));

        return compact(
            'tabAktif', 'bulanAktif', 'namaDetailHutangPelanggan', 'idPenjualanDiedit',
            'modalHutangTerbuka', 'namaModalHutang', 'tampilkanFormTransaksi',
            'tanggalTransaksi', 'namaTransaksi', 'jumlahTransaksi', 'dibayarTransaksi', 'dibayarTransaksiDisentuh', 'catatanTransaksi',
            'namaManajemenStok', 'hargaManajemenStok',
            'namaSisaStok', 'jumlahSisaStok', 'hargaSisaStok',
            'daftarProduk', 'daftarPenjualan', 'daftarPengeluaranHari',
            'daftarStokMinyak', 'daftarManajemenStok', 'daftarSisaStok',
            'daftarNamaHutangPelanggan', 'hutangPelangganDiproses', 'totalHutangPelanggan', 'maxCols', 'entriHutangPelanggan',
            'personalLedgers', 'saldoLogs',
            'rekapPerProduk', 'totalRekap', 'rekapBulanPerProduk', 'totalRekapBulan',
            'semuaNamaPelanggan', 'namaPemegangManajemenStok', 'dataRingkasan',
            'labelBulan', 'parameterQuery', 'asetHari', 'hutangPribadiHari', 'saldoHari',
            'saldoTerakhir', 'stokMinyakTersimpan', 'barisManajemenStok', 'barisSisaStok', 'barisHutangPribadi',
            'maksKarungManajemenStok', 'totalManajemenStok', 'totalSisaStok', 'totalHutangPribadi'
        );
    }

    /**
     * Neraca tab Ringkasan mengikuti lembar tanggal aktif (per hari, bukan bulan).
     *
     * @return array<string, mixed>
     */
    private function ringkasan(string $bulanAktif, string $tanggalTransaksi, int $totalHutangPelanggan): array
    {
        $totalStokMinyak = StokMinyak::where('tanggal', $tanggalTransaksi)->get()->sum(fn ($o) => $o->jumlah * $o->harga);
        $totalManajemenStok = ManajemenStok::where(fn ($q) => $q->where('tanggal', $tanggalTransaksi)->orWhereNull('tanggal'))->get()->sum(fn ($o) => $o->subtotal());
        $totalSisaStok = SisaStok::where('tanggal', $tanggalTransaksi)->get()->sum(fn ($o) => $o->jumlah * $o->harga);
        $totalHutangPribadi = (int) BukuPribadi::where('tanggal', $tanggalTransaksi)->sum('jumlah');
        $totalSaldo = (int) PotonganSaldo::where('tanggal', $tanggalTransaksi)->get()->sum(fn ($s) => $s->hasil());
        $totalKeseluruhan = $totalStokMinyak + $totalManajemenStok + $totalHutangPelanggan + $totalSisaStok;

        [$mulaiBulan, $akhirBulan] = $this->rentangBulan($bulanAktif);

        $penerimaanPerHari = Penjualan::whereBetween('tanggal', [$mulaiBulan, $akhirBulan])
            ->selectRaw('tanggal, SUM(dibayar) AS dibayar, SUM(dibayar_kemarin) AS dibayar_kemarin')
            ->groupBy('tanggal')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->tanggal)->format('Y-m-d'));
        $pengeluaranPerHari = Pengeluaran::whereBetween('tanggal', [$mulaiBulan, $akhirBulan])
            ->selectRaw('tanggal, SUM(jumlah) AS total')
            ->groupBy('tanggal')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->tanggal)->format('Y-m-d'));

        $grafikHarian = [];
        $penunjuk = \DateTimeImmutable::createFromFormat('Y-m-d', $mulaiBulan);
        $tanggalTerakhir = \DateTimeImmutable::createFromFormat('Y-m-d', $akhirBulan);
        while ($penunjuk <= $tanggalTerakhir) {
            $kunci = $penunjuk->format('Y-m-d');
            $diterima = isset($penerimaanPerHari[$kunci])
                ? (int) $penerimaanPerHari[$kunci]->dibayar + (int) $penerimaanPerHari[$kunci]->dibayar_kemarin
                : 0;
            $pengeluaran = isset($pengeluaranPerHari[$kunci]) ? (int) $pengeluaranPerHari[$kunci]->total : 0;
            $grafikHarian[] = [
                'tanggal' => $kunci,
                'label' => (string) (int) $penunjuk->format('j'),
                'diterima' => $diterima,
                'pengeluaran' => $pengeluaran,
                'bersih' => $diterima - $pengeluaran,
            ];
            $penunjuk = $penunjuk->modify('+1 day');
        }

        $produkTerlaris = ItemPenjualan::query()
            ->join('penjualan', 'penjualan.id', '=', 'item_penjualan.penjualan_id')
            ->whereBetween('penjualan.tanggal', [$mulaiBulan, $akhirBulan])
            ->groupBy('item_penjualan.nama')
            ->selectRaw('item_penjualan.nama AS nama, SUM(item_penjualan.jumlah) AS jumlah, SUM(item_penjualan.jumlah * item_penjualan.harga) AS nilai')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        $pelangganAktif = Penjualan::whereBetween('tanggal', [$mulaiBulan, $akhirBulan])
            ->groupBy('nama')
            ->selectRaw('nama, COUNT(*) AS trx, SUM(dibayar) + SUM(dibayar_kemarin) AS bayar')
            ->orderByDesc('bayar')
            ->get()
            ->map(fn ($r) => ['nama' => $r->nama, 'trx' => (int) $r->trx, 'bayar' => (int) $r->bayar])
            ->values();

        return compact(
            'totalStokMinyak', 'totalManajemenStok', 'totalSisaStok', 'totalHutangPelanggan', 'totalHutangPribadi',
            'totalSaldo', 'totalKeseluruhan', 'bulanAktif', 'tanggalTransaksi',
            'grafikHarian', 'produkTerlaris', 'pelangganAktif'
        );
    }
}
