<?php

namespace App\Services;

/**
 * Parser rule-based untuk ekstraksi data transaksi dari transkrip speech-to-text.
 * Normalisasi teks -> pemetaan kata-angka -> pencocokan nama produk (exact + Levenshtein)
 * -> ekstraksi jumlah per produk (dengan satuan) -> nama pelanggan & catatan.
 */
class PenguraiUcapan
{
    private const FILLERS = [
        'eh', 'eeh', 'e', 'hm', 'hmm', 'mm', 'mmm', 'anu', 'nah', 'jadi',
        'saya', 'mau', 'beli', 'pesan', 'minta', 'tolong', 'untuk', 'buat',
        'ya', 'yah', 'dong', 'nih', 'bang', 'pak', 'mbak', 'mas', 'bu', 'kak',
        'om', 'gan', 'sis', 'master', 'wkwk', 'hehe', 'iya', 'yep', 'oke',
        'kasih', 'dulu', 'kan', 'deh', 'maaf', 'permisi', 'numpang', 'juga',
        'dan', 'serta', 'lalu', 'terus', 'dengan', 'kerupuk',
    ];

    /**
     * Kata satuan/kategori yang tidak boleh masuk nama pelanggan. Catatan tetap
     * boleh memuat satuan karena pengguna sering menulis keterangan seperti
     * "bungkus terpisah".
     */
    private const NON_NAME_WORDS = [
        'kerupuk', 'kilo', 'kilos', 'kg', 'kgs', 'kilogram', 'kilograms',
        'ons', 'onsa', 'once', 'gram', 'gr', 'biji', 'buah', 'pcs', 'pak',
        'bungkus', 'kardus', 'dus', 'lusin',
    ];

    private const DIGITS = [
        'nol' => 0, 'satu' => 1, 'dua' => 2, 'tiga' => 3, 'empat' => 4,
        'lima' => 5, 'enam' => 6, 'tujuh' => 7, 'delapan' => 8, 'sembilan' => 9,
    ];

    private const SCALES = [
        'sepuluh', 'sebelas', 'belas', 'puluh', 'ratus', 'seratus',
        'ribu', 'seribu', 'koma', 'setengah',
    ];

    /** satuan -> faktor konversi ke kg */
    private const UNITS = [
        'kg' => 1.0, 'kgs' => 1.0, 'kilo' => 1.0, 'kilos' => 1.0,
        'kilogram' => 1.0, 'kilograms' => 1.0,
        'ons' => 0.1, 'onsa' => 0.1, 'once' => 0.1,
        'gram' => 0.001, 'gr' => 0.001,
        'biji' => 1.0, 'buah' => 1.0, 'pcs' => 1.0, 'pak' => 1.0,
        'bungkus' => 1.0, 'kardus' => 1.0, 'dus' => 1.0, 'lusin' => 1.0,
    ];

    /**
     * @param  string  $raw  transkrip asli dari browser
     * @param  iterable  $daftarProduk  koleksi {id,name,price}
     * @return array{raw:string,normalized:string,name:?string,items:array,note:?string,total_kg:float,total:int}
     */
    public function urai(string $raw, iterable $daftarProduk): array
    {
        $deretToken = $this->pecahToken($raw);
        $tokenNormal = $this->normalisasiToken($deretToken);

        $daftarAlias = [];
        foreach ($daftarProduk as $produk) {
            $tokenAlias = array_map(fn ($t) => mb_strtolower($t), $this->pecahToken((string) $produk->nama));
            $daftarAlias[] = ['product' => $produk, 'tokens' => $tokenAlias];
        }

        $cocokan = $this->cariProduk($deretToken, $tokenNormal, $daftarAlias);
        $terpakai = [];
        foreach ($cocokan as $m) {
            for ($i = $m['start']; $i <= $m['end']; $i++) {
                $terpakai[$i] = true;
            }
        }

        $daftarItem = [];
        foreach ($cocokan as $idx => $m) {
            [$jumlah, $unit, $consumed] = $this->ambilJumlah($tokenNormal, $m['start'], $m['end'], $terpakai, $cocokan);
            foreach ($consumed as $ci) {
                $terpakai[$ci] = true;
            }
            $produk = $m['product'];
            $daftarItem[] = [
                'product_id' => (int) $produk->id,
                'name' => (string) $produk->nama,
                'price' => (int) $produk->harga,
                'qty' => round($jumlah, 3),
                'unit' => $unit,
                'subtotal' => (int) round($jumlah * (int) $produk->harga),
                'confidence' => $m['confidence'],
            ];
        }

        $daftarItem = $this->gabungProdukBerulang($daftarItem);

        $nama = $this->ambilNamaPelanggan($deretToken, $tokenNormal, $terpakai, $cocokan);
        $catatan = $this->ambilCatatan($deretToken, $tokenNormal, $terpakai, $cocokan);

        $teksNormal = implode(' ', array_values(array_filter($tokenNormal, fn ($n) => $n !== '')));

        return [
            'raw' => $raw,
            'normalized' => $teksNormal,
            'name' => $nama,
            'items' => $daftarItem,
            'note' => $catatan,
            'total_kg' => round(array_sum(array_column($daftarItem, 'qty')), 3),
            'total' => (int) array_sum(array_column($daftarItem, 'subtotal')),
        ];
    }

    /** Potong kalimat menjadi token kata/angka (memisahkan "25kg" jadi "25","kg"). */
    public function pecahToken(string $teks): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?|\p{L}+/u', $teks, $m);

        return $m[0];
    }

    /**
     * Ubah tiap token menjadi bentuk normal:
     * - angka digit -> nilai numerik ("2,5" -> "2.5", "1.000" -> "1000")
     * - deret kata angka -> nilai numerik ("dua puluh lima" -> "25")
     * - kata lain -> huruf kecil + buang imbuhan "nya"
     */
    private function normalisasiToken(array $deretToken): array
    {
        $tokenNormal = [];
        $n = count($deretToken);
        $i = 0;
        while ($i < $n) {
            $w = $deretToken[$i];
            $hurufKecil = mb_strtolower($w);

            if (preg_match('/^\d+([.,]\d+)?$/', $w)) {
                $nilai = $this->nilaiNumerik($w);
                $berikutnya = $i + 1;
                // deret kata angka setelah digit: "2 setengah" -> 2.5, "2 koma lima" -> 2.5
                if ($berikutnya < $n && $this->kataAngka(mb_strtolower($deretToken[$berikutnya]))) {
                    [$nilai, $berikutnya] = $this->konsumsiKataAngka($deretToken, $berikutnya, $n, $nilai);
                }
                for ($j = $i; $j < $berikutnya; $j++) {
                    $tokenNormal[$j] = '';
                }
                $tokenNormal[$berikutnya - 1] = $this->teksAngka((float) $nilai);
                $i = $berikutnya;

                continue;
            }

            if ($this->kataAngka($hurufKecil)) {
                [$nilai, $berikutnya] = $this->konsumsiKataAngka($deretToken, $i, $n);
                for ($j = $i; $j < $berikutnya; $j++) {
                    $tokenNormal[$j] = '';
                }
                $tokenNormal[$berikutnya - 1] = $this->teksAngka($nilai);
                $i = $berikutnya;

                continue;
            }

            $tokenNormal[$i] = $this->akarKata($hurufKecil);
            $i++;
        }

        return $this->gabungNilaiSetengah($tokenNormal);
    }

    /**
     * Sisa "setengah" yang terpisah dari angkanya:
     * "2 setengah" -> 2.5, "dua kilo setengah" -> 2.5 (angka, lalu satuan).
     */
    private function gabungNilaiSetengah(array $tokenNormal): array
    {
        foreach (array_keys($tokenNormal) as $i) {
            if ($tokenNormal[$i] === '' || ! is_numeric($tokenNormal[$i]) || abs((float) $tokenNormal[$i] - 0.5) > 1e-9) {
                continue;
            }
            $prev = $i - 1;
            if ($prev >= 0 && $tokenNormal[$prev] !== '' && is_numeric($tokenNormal[$prev])) {
                $tokenNormal[$prev] = $this->teksAngka((float) $tokenNormal[$prev] + 0.5);
                $tokenNormal[$i] = '';

                continue;
            }
            if ($prev >= 1 && $tokenNormal[$prev] !== '' && $this->faktorSatuan(mb_strtolower($tokenNormal[$prev])) !== null
                && $tokenNormal[$prev - 1] !== '' && is_numeric($tokenNormal[$prev - 1])) {
                $tokenNormal[$prev - 1] = $this->teksAngka((float) $tokenNormal[$prev - 1] + 0.5);
                $tokenNormal[$i] = '';
            }
        }

        return $tokenNormal;
    }

    private function nilaiNumerik(string $token): string
    {
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $token)) {
            return (string) (int) str_replace('.', '', $token);
        }

        return (string) (float) str_replace(',', '.', $token);
    }

    private function teksAngka(float $nilai): string
    {
        return (string) $nilai;
    }

    private function kataAngka(string $kata): bool
    {
        $stem = $this->akarKata(mb_strtolower($kata));

        return isset(self::DIGITS[$stem]) || in_array($stem, self::SCALES, true);
    }

    /**
     * Konsumsi deret kata angka ("dua puluh lima koma lima") mulai dari posisi $i.
     * $awal = nilai awal dari digit sebelumnya ("2" + "setengah" -> 2.5).
     *
     * @return array{0:float,1:int}
     */
    private function konsumsiKataAngka(array $deretToken, int $i, int $n, ?float $awal = null): array
    {
        $hasil = 0;
        $current = $awal ?? 0;
        $adaSkala = false;
        $teksDesimal = null;
        $started = $awal !== null;
        $end = $i;

        while ($end < $n) {
            $raw = mb_strtolower($deretToken[$end]);
            $w = $this->akarKata($raw);

            if ($w === 'koma') {
                if ($teksDesimal === null) {
                    $teksDesimal = '';
                    $started = true;
                    $end++;

                    continue;
                }
                break;
            }

            if ($teksDesimal !== null) {
                if (isset(self::DIGITS[$w])) {
                    $teksDesimal .= self::DIGITS[$w];
                    $end++;

                    continue;
                }
                if (preg_match('/^\d+$/', $raw)) {
                    $teksDesimal .= $raw;
                    $end++;

                    continue;
                }
                break;
            }

            if (isset(self::DIGITS[$w])) {
                $d = self::DIGITS[$w];
                if ($adaSkala) {
                    $hasil += $current;
                    $current = $d;
                    $adaSkala = false;
                } else {
                    $current = $current * 10 + $d;
                }
                $started = true;
                $end++;

                continue;
            }

            switch ($w) {
                case 'sepuluh':
                    $current = ($current ?: 1) * 10;
                    $adaSkala = true;
                    break;
                case 'sebelas':
                    $current = ($current ?: 1) * 10 + 1;
                    $adaSkala = true;
                    break;
                case 'puluh':
                    $current = ($current ?: 1) * 10;
                    $adaSkala = true;
                    break;
                case 'ratus':
                case 'seratus':
                    $current = ($current ?: 1) * 100;
                    $adaSkala = true;
                    break;
                case 'ribu':
                case 'seribu':
                    $hasil += ($current ?: 1) * 1000;
                    $current = 0;
                    $adaSkala = false;
                    break;
                case 'belas':
                    if ($current <= 0) {
                        return $started ? [($hasil + $current), max($end, $i + 1)] : [0.0, $i];
                    }
                    $current += 10;
                    break;
                case 'setengah':
                    $current += 0.5;
                    break;
                default:
                    return $started ? [($hasil + $current), max($end, $i + 1)] : [0.0, $i];
            }

            $started = true;
            $end++;
        }

        if (! $started) {
            return [0.0, $i];
        }

        $nilai = $hasil + $current;
        if ($teksDesimal !== null && $teksDesimal !== '') {
            $nilai = (float) ((string) $nilai.'.'.$teksDesimal);
        }

        return [$nilai, max($end, $i + 1)];
    }

    private function akarKata(string $kata): string
    {
        foreach (['nya', 'ku', 'mu', 'lah', 'kah', 'pun'] as $suffix) {
            if (mb_strlen($kata) > strlen($suffix) + 2 && str_ends_with($kata, $suffix)) {
                return mb_substr($kata, 0, -strlen($suffix));
            }
        }

        return $kata;
    }

    /**
     * Cari penyebutan nama produk pada deret token.
     * Pass 1: exact + fuzzy ketat (Levenshtein terbatas).
     * Pass 2: longgar untuk token yang belum terpakai (transkrip noise).
     */
    private function cariProduk(array $deretToken, array $tokenNormal, array $daftarAlias): array
    {
        $n = count($deretToken);
        $kandidat = [];

        foreach ($daftarAlias as $alias) {
            $tokenAlias = $alias['tokens'];
            $panjang = count($tokenAlias);
            if ($panjang === 0 || $panjang > $n) {
                continue;
            }
            for ($i = 0; $i + $panjang <= $n; $i++) {
                $confidence = $this->kualitasCocok($tokenNormal, $i, $tokenAlias);
                if ($confidence !== null) {
                    $kandidat[] = [
                        'start' => $i,
                        'end' => $i + $panjang - 1,
                        'product' => $alias['product'],
                        'confidence' => $confidence,
                    ];
                }
            }
        }

        $terpilih = $this->pilihSerakah($kandidat);

        // pass longgar: hanya token yang belum terpakai oleh pass ketat
        $tercakup = array_fill(0, $n, false);
        foreach ($terpilih as $m) {
            for ($i = $m['start']; $i <= $m['end']; $i++) {
                $tercakup[$i] = true;
            }
        }

        $kandidatLonggar = [];
        foreach ($daftarAlias as $alias) {
            $tokenAlias = $alias['tokens'];
            $panjang = count($tokenAlias);
            if ($panjang === 0 || $panjang > $n) {
                continue;
            }
            for ($i = 0; $i + $panjang <= $n; $i++) {
                $blocked = false;
                for ($k = 0; $k < $panjang; $k++) {
                    if ($tercakup[$i + $k] || $this->lewatiLonggar($tokenNormal[$i + $k] ?? '')) {
                        $blocked = true;
                        break;
                    }
                }
                if ($blocked) {
                    continue;
                }
                if ($this->kualitasCocokLonggar($tokenNormal, $i, $tokenAlias)) {
                    $kandidatLonggar[] = [
                        'start' => $i,
                        'end' => $i + $panjang - 1,
                        'product' => $alias['product'],
                        'confidence' => 'loose',
                    ];
                }
            }
        }

        $terpilih = array_merge($terpilih, $this->pilihSerakah($kandidatLonggar));
        usort($terpilih, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $terpilih;
    }

    private function pilihSerakah(array $kandidat): array
    {
        usort($kandidat, function ($a, $b) {
            if ($a['start'] !== $b['start']) {
                return $a['start'] <=> $b['start'];
            }
            $rank = ['exact' => 3, 'tight' => 2, 'loose' => 1];
            $ra = $rank[$a['confidence']] ?? 0;
            $rb = $rank[$b['confidence']] ?? 0;
            if ($ra !== $rb) {
                return $rb <=> $ra;
            }

            return ($b['end'] - $b['start']) <=> ($a['end'] - $a['start']);
        });

        $terpilih = [];
        $lastEnd = -1;
        foreach ($kandidat as $c) {
            if ($c['start'] > $lastEnd) {
                $terpilih[] = $c;
                $lastEnd = $c['end'];
            }
        }

        return $terpilih;
    }

    /** token yang tidak boleh dipakai pass longgar: angka & satuan */
    private function lewatiLonggar(string $norm): bool
    {
        if ($norm === '') {
            return true;
        }
        if (is_numeric($norm)) {
            return true;
        }

        return isset(self::UNITS[mb_strtolower($norm)]);
    }

    /**
     * @return ?string 'exact'|'tight' kalau cocok, null kalau tidak
     */
    private function kualitasCocok(array $tokenNormal, int $offset, array $tokenAlias): ?string
    {
        $exact = true;
        foreach ($tokenAlias as $k => $satuTokenAlias) {
            $val = $tokenNormal[$offset + $k] ?? '';
            if ($val === $satuTokenAlias) {
                continue;
            }
            if ($val !== '' && is_numeric($val) && is_numeric($satuTokenAlias) && (float) $val === (float) $satuTokenAlias) {
                continue;
            }
            if ($val === '' || $this->jarakEdit($val, $satuTokenAlias) > $this->maksimalEdit(mb_strlen($satuTokenAlias))) {
                return null;
            }
            $exact = false;
        }

        return $exact ? 'exact' : 'tight';
    }

    /**
     * Pass longgar untuk transkrip berisik: jarak <= separuh panjang kata
     * (gelung vs belum = 3/6 = 0.5). Hanya untuk nama produk >= 4 huruf.
     */
    private function kualitasCocokLonggar(array $tokenNormal, int $offset, array $tokenAlias): bool
    {
        foreach ($tokenAlias as $k => $satuTokenAlias) {
            $val = $tokenNormal[$offset + $k] ?? '';
            $aliasLen = mb_strlen($satuTokenAlias);
            $valLen = mb_strlen($val);

            if ($aliasLen < 4 || $valLen < 4) {
                return false;
            }
            if ($val === $satuTokenAlias) {
                continue;
            }
            if ($val !== '' && is_numeric($val) && is_numeric($satuTokenAlias) && (float) $val === (float) $satuTokenAlias) {
                continue;
            }
            $jarak = $this->jarakEdit($val, $satuTokenAlias);
            if ($jarak > (int) floor(max($aliasLen, $valLen) / 2)) {
                return false;
            }
        }

        return true;
    }

    /** Produk sama disebut lebih dari sekali: nilai terakhir menang. */
    private function gabungProdukBerulang(array $daftarItem): array
    {
        $merged = [];
        foreach ($daftarItem as $itemPenjualan) {
            $pid = $itemPenjualan['product_id'];
            if (isset($merged[$pid])) {
                $merged[$pid]['qty'] = $itemPenjualan['qty'];
                $merged[$pid]['unit'] = $itemPenjualan['unit'];
                $merged[$pid]['subtotal'] = $itemPenjualan['subtotal'];
                $merged[$pid]['confidence'] = $itemPenjualan['confidence'];

                continue;
            }
            $merged[$pid] = $itemPenjualan;
        }

        return array_values($merged);
    }

    private function maksimalEdit(int $panjang): int
    {
        if ($panjang <= 3) {
            return 0;
        }
        if ($panjang <= 5) {
            return 1;
        }

        return 2;
    }

    private function jarakEdit(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }
        $jarak = levenshtein($a, $b);

        return $jarak === false ? PHP_INT_MAX : $jarak;
    }

    /**
     * Ambil jumlah untuk satu penyebutan produk:
     * (a) angka+satuan di kiri, (b) angka+satuan di kanan,
     * (c) angka apa pun di kiri, (d) angka apa pun di kanan, (e) default 1.
     *
     * @return array{0:float,1:?string,2:array}
     */
    private function ambilJumlah(array $tokenNormal, int $start, int $end, array $terpakai, array $cocokan): array
    {
        $left = [];
        $boundL = 0;
        foreach ($cocokan as $m) {
            if ($m['end'] < $start) {
                $boundL = max($boundL, $m['end'] + 1);
            }
        }
        for ($i = $start - 1; $i >= $boundL && $i >= 0; $i--) {
            $left[] = ['i' => $i, 'norm' => $tokenNormal[$i]];
        }

        $right = [];
        $boundR = count($tokenNormal);
        foreach ($cocokan as $m) {
            if ($m['start'] > $end) {
                $boundR = min($boundR, $m['start']);
            }
        }
        for ($i = $end + 1; $i < $boundR; $i++) {
            $right[] = ['i' => $i, 'norm' => $tokenNormal[$i]];
        }

        foreach ([$left, $right] as $window) {
            $hit = $this->jumlahDenganSatuan($window, $terpakai);
            if ($hit !== null) {
                return $hit;
            }
        }
        foreach ([$left, $right] as $window) {
            $hit = $this->jumlahAngka($window, $terpakai);
            if ($hit !== null) {
                return $hit;
            }
        }

        return [1.0, null, []];
    }

    private function jumlahDenganSatuan(array $window, array $terpakai): ?array
    {
        foreach ($window as $idx => $entri) {
            $unit = $this->faktorSatuan(mb_strtolower($entri['norm']));
            if ($unit === null) {
                continue;
            }
            foreach ([$idx - 1, $idx + 1] as $nIdx) {
                if (! isset($window[$nIdx])) {
                    continue;
                }
                $nei = $window[$nIdx];
                if (isset($terpakai[$nei['i']]) || ! is_numeric($nei['norm']) || $nei['norm'] === '') {
                    continue;
                }

                return [(float) $nei['norm'] * $unit['factor'], $unit['label'], [$entri['i'], $nei['i']]];
            }
        }

        return null;
    }

    private function jumlahAngka(array $window, array $terpakai): ?array
    {
        foreach ($window as $entri) {
            if (isset($terpakai[$entri['i']])) {
                continue;
            }
            if ($entri['norm'] !== '' && is_numeric($entri['norm'])) {
                $nilai = (float) $entri['norm'];
                if ($nilai <= 0) {
                    continue;
                }

                return [$nilai, 'kg', [$entri['i']]];
            }
        }

        return null;
    }

    private function faktorSatuan(string $kata): ?array
    {
        if (! isset(self::UNITS[$kata])) {
            return null;
        }

        return ['factor' => self::UNITS[$kata], 'label' => $kata];
    }

    private function ambilNamaPelanggan(array $deretToken, array $tokenNormal, array $terpakai, array $cocokan): ?string
    {
        $pertama = $cocokan[0]['start'] ?? count($deretToken);
        $bagian = [];
        for ($i = 0; $i < $pertama; $i++) {
            if (isset($terpakai[$i])) {
                continue;
            }
            $hurufKecil = mb_strtolower($deretToken[$i]);
            if (in_array($hurufKecil, self::FILLERS, true) || in_array($hurufKecil, self::NON_NAME_WORDS, true)) {
                continue;
            }
            if (($tokenNormal[$i] ?? '') === '' || is_numeric($tokenNormal[$i])) {
                continue;
            }
            $bagian[] = $deretToken[$i];
            if (count($bagian) >= 6) {
                break;
            }
        }

        $nama = trim(implode(' ', $bagian));

        return $nama === '' ? null : $nama;
    }

    private function ambilCatatan(array $deretToken, array $tokenNormal, array $terpakai, array $cocokan): ?string
    {
        if (empty($cocokan)) {
            return null;
        }
        $pertama = $cocokan[0]['start'];
        $terakhir = $cocokan[count($cocokan) - 1]['end'];
        $bagian = [];
        for ($i = $pertama + 1; $i < count($deretToken); $i++) {
            if ($i <= $terakhir || isset($terpakai[$i])) {
                continue;
            }
            $hurufKecil = mb_strtolower($deretToken[$i]);
            if (in_array($hurufKecil, self::FILLERS, true)) {
                continue;
            }
            if (($tokenNormal[$i] ?? '') === '') {
                continue;
            }
            $bagian[] = $deretToken[$i];
        }

        $catatan = trim(implode(' ', $bagian));

        return $catatan === '' ? null : $catatan;
    }
}
