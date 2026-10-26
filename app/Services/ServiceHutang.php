<?php

namespace App\Services;

use App\Models\BukuPelanggan;
use App\Models\Penjualan;
use Illuminate\Support\Carbon;

/**
 * Rekap sisa hutang pelanggan untuk tabel hutang di tab Hasil.
 *
 * Tabel menampilkan hutang aktif per pelanggan. Data diisi manual lewat
 * tambah entri, edit nominal, atau hapus; total per pelanggan dan total
 * keseluruhan dihitung dari sisa aktif. Pelanggan diurutkan berdasarkan nama,
 * sedangkan rincian hutang mengikuti tanggal entri.
 *
 * Fungsi ini hanya menghitung sisa untuk tampilan; tidak menambah, mengubah,
 * atau menghapus data. Entri yang sisanya 0 tidak ditampilkan.
 */
class ServiceHutang
{
    /**
     * Sisa hutang satu pelanggan pada sebuah lembar tanggal:
     * activeDebts (id, amount, remaining, date), totalSisa, dan deposit
     * (kelebihan pengurang yang melebihi seluruh entri).
     */
    public function prosesHutangPelanggan(string $nama, ?string $tanggalLembar = null): array
    {
        $daftarEntri = BukuPelanggan::untukLembar($tanggalLembar)
            ->where('nama', $nama)
            ->orderBy('tanggal')->orderBy('id')->get();
        $debts = [];
        $deposit = 0;
        foreach ($daftarEntri as $l) {
            if ($l->jenis === 'tambah') {
                $debts[] = ['id' => $l->id, 'amount' => $l->jumlah, 'remaining' => $l->jumlah, 'date' => Carbon::parse($l->tanggal)->format('Y-m-d')];
            } else {
                // Entri pengurang dihitung dari entri terbaru lebih dahulu, lalu ke
                // entri yang lebih lama. Perhitungan ini hanya untuk tampilan.
                $pembayaran = $l->jumlah;
                for ($i = count($debts) - 1; $i >= 0 && $pembayaran > 0; $i--) {
                    $potongan = min($pembayaran, $debts[$i]['remaining']);
                    $debts[$i]['remaining'] -= $potongan;
                    $pembayaran -= $potongan;
                }
                if ($pembayaran > 0) {
                    $deposit += $pembayaran;
                }
            }
        }
        $activeDebts = array_values(array_filter($debts, fn ($d) => $d['remaining'] > 0));
        $totalSisa = (int) array_sum(array_column($activeDebts, 'remaining'));

        return compact('activeDebts', 'totalSisa', 'deposit');
    }

    /** Riwayat lengkap satu pelanggan (untuk panel detail) dengan saldo berjalan. */
    public function entriDetailHutangPelanggan(string $nama, ?string $tanggalLembar = null)
    {
        $daftarEntri = BukuPelanggan::untukLembar($tanggalLembar)
            ->where('nama', $nama)
            ->orderBy('tanggal')->orderBy('id')->get();
        $saldoBerjalan = 0;
        $daftarPenjualan = Penjualan::whereIn('id', $daftarEntri->pluck('penjualan_id')->filter()->unique())->get()->keyBy('id');

        return $daftarEntri->map(function ($l) use (&$saldoBerjalan, $daftarPenjualan) {
            $saldoBerjalan += $l->jenis === 'tambah' ? $l->jumlah : -$l->jumlah;

            return (object) [
                'id' => $l->id, 'tanggal' => Carbon::parse($l->tanggal)->format('Y-m-d'),
                'jenis' => $l->jenis, 'jumlah' => $l->jumlah, 'running' => $saldoBerjalan,
                'catatan' => $l->catatan, 'penjualan_id' => $l->penjualan_id,
                'dibayar' => $l->penjualan_id && isset($daftarPenjualan[$l->penjualan_id]) ? $daftarPenjualan[$l->penjualan_id]->dibayar : null,
            ];
        });
    }
}
