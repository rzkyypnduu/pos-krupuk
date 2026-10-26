<?php

namespace App\Services;

use App\Models\BukuPelanggan;
use App\Models\BukuPribadi;
use App\Models\LembarHasil;
use App\Models\ManajemenStok;
use App\Models\PotonganSaldo;
use App\Models\SisaStok;
use App\Models\StokMinyak;
use Illuminate\Support\Facades\DB;

/**
 * Lembar hasil (tab Hasil) adalah lembar harian per tanggal.
 * Saat pertama kali membuka tanggal yang masih kosong, isi tab hasil disalin
 * dari tanggal sumber; setelah itu kedua tanggal independen. Edit pada satu
 * tanggal tidak memengaruhi tanggal lain.
 */
class ServiceLembarHasil
{
    /**
     * Siapkan lembar hasil untuk tanggal aktif; proses sekali per tanggal.
     * Jika tanggal target masih kosong, seluruh isi tab hasil disalin dari
     * tanggal sumber; setelah itu kedua tanggal independen.
     */
    public function pastikanLembar(string $tanggalTransaksi): void
    {
        if (LembarHasil::where('tanggal', $tanggalTransaksi)->exists()) {
            return;
        }
        if ($this->adaData($tanggalTransaksi)) {
            LembarHasil::create(['tanggal' => $tanggalTransaksi, 'tanggal_sumber' => null]);

            return;
        }
        $sumber = $this->cariSumber($tanggalTransaksi);
        if ($sumber === null) {
            return; // belum ada sumber — diproses lagi pada kunjungan berikutnya
        }
        DB::transaction(function () use ($sumber, $tanggalTransaksi) {
            $this->salin($sumber, $tanggalTransaksi);
            LembarHasil::create(['tanggal' => $tanggalTransaksi, 'tanggal_sumber' => $sumber]);
        });
    }

    /** Apakah tanggal itu sudah punya isi tab hasil? */
    public function adaData(string $tanggal): bool
    {
        return StokMinyak::where('tanggal', $tanggal)->exists()
            || ManajemenStok::where('tanggal', $tanggal)->exists()
            || SisaStok::where('tanggal', $tanggal)->exists()
            || BukuPribadi::where('tanggal', $tanggal)->exists()
            || PotonganSaldo::where('tanggal', $tanggal)->exists()
            || BukuPelanggan::where('tanggal_lembar', $tanggal)->exists();
    }

    /** Sumber salinan: tanggal terakhir yang dilihat, atau tanggal terdekat yang punya data. */
    private function cariSumber(string $tanggalTransaksi): ?string
    {
        $terakhir = session('hasil_last_date');
        if (is_string($terakhir) && $terakhir !== $tanggalTransaksi && preg_match('/^\d{4}-\d{2}-\d{2}$/', $terakhir) && $this->adaData($terakhir)) {
            return $terakhir;
        }

        $sebelum = null;
        $sesudah = null;
        foreach ($this->batasTanggal($tanggalTransaksi) as $batas) {
            if ($batas['before'] !== null && ($sebelum === null || $batas['before'] > $sebelum)) {
                $sebelum = $batas['before'];
            }
            if ($batas['after'] !== null && ($sesudah === null || $batas['after'] < $sesudah)) {
                $sesudah = $batas['after'];
            }
        }

        return $sebelum ?? $sesudah;
    }

    /** Batas tanggal yang punya data per tabel (kolom date; pelanggan memakai sheet_date). */
    private function batasTanggal(string $tanggalTransaksi): array
    {
        $bounds = [];
        foreach ([StokMinyak::class, ManajemenStok::class, SisaStok::class, BukuPribadi::class, PotonganSaldo::class] as $model) {
            $bounds[] = [
                'before' => $model::where('tanggal', '<', $tanggalTransaksi)->max('tanggal'),
                'after' => $model::where('tanggal', '>', $tanggalTransaksi)->min('tanggal'),
            ];
        }
        $bounds[] = [
            'before' => BukuPelanggan::whereNotNull('tanggal_lembar')->where('tanggal_lembar', '<', $tanggalTransaksi)->max('tanggal_lembar'),
            'after' => BukuPelanggan::whereNotNull('tanggal_lembar')->where('tanggal_lembar', '>', $tanggalTransaksi)->min('tanggal_lembar'),
        ];

        return $bounds;
    }

    /** Salin seluruh isi tab hasil dari tanggal sumber ke tanggal target (independen). */
    private function salin(string $sumber, string $tujuan): void
    {
        $daftarModel = [StokMinyak::class, ManajemenStok::class, SisaStok::class, BukuPribadi::class, PotonganSaldo::class];
        foreach ($daftarModel as $model) {
            foreach ($model::where('tanggal', $sumber)->get() as $baris) {
                $salinan = $baris->replicate();
                $salinan->tanggal = $tujuan;
                $salinan->save();
            }
        }
        // Rekap hutang pelanggan: entri disalin dengan lembar baru; tanggal riwayat
        // tetap asli supaya catatan peristiwa tidak ikut bergeser.
        foreach (BukuPelanggan::where('tanggal_lembar', $sumber)->get() as $baris) {
            $salinan = $baris->replicate();
            $salinan->tanggal_lembar = $tujuan;
            $salinan->save();
        }
    }
}
