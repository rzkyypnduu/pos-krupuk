<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Services\PenguraiUcapan;
use Illuminate\Http\Request;

/**
 * Speech-to-text untuk form Transaksi Baru: terima kandidat teks (N-best)
 * dari browser, pilih hasil urai terbaik, kirim balik untuk mengisi form.
 */
class UcapanController extends Controller
{
    /** Skor kandidat N-best: per item cocok, bonus bila nama terisi, penalti per item "longgar". */
    private const SKOR_PER_ITEM = 10;

    private const SKOR_BONUS_NAMA = 5;

    public function __construct(private PenguraiUcapan $pengurai) {}

    public function uraiUcapan(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:2000',
            'alternatives' => 'sometimes|array|max:5',
            'alternatives.*' => 'string|max:2000',
        ], [
            'text.required' => 'Teks suara kosong.',
        ]);

        $daftarProduk = Produk::orderBy('nama')->get();

        // Browser mengirim beberapa kandidat hasil N-best; pilih yang paling masuk akal.
        $daftarKandidat = [$request->input('text', '')];
        foreach ((array) $request->input('alternatives', []) as $alternatif) {
            $alternatif = trim((string) $alternatif);
            if ($alternatif !== '' && ! in_array($alternatif, $daftarKandidat, true)) {
                $daftarKandidat[] = $alternatif;
            }
        }

        $terbaik = null;
        $skorTerbaik = PHP_INT_MIN;
        foreach ($daftarKandidat as $kandidat) {
            $hasilUrai = $this->pengurai->urai($kandidat, $daftarProduk);
            $longgar = count(array_filter($hasilUrai['items'], fn ($i) => $i['confidence'] === 'loose'));
            $skor = count($hasilUrai['items']) * self::SKOR_PER_ITEM
                + ($hasilUrai['name'] !== null && $hasilUrai['name'] !== '' ? self::SKOR_BONUS_NAMA : 0)
                - $longgar;
            if ($skor > $skorTerbaik) {
                $skorTerbaik = $skor;
                $terbaik = $hasilUrai;
            }
        }

        return response()->json(['ok' => true, 'candidates' => count($daftarKandidat)] + $terbaik);
    }
}
