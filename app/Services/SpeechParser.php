<?php

namespace App\Services;

/**
 * Parser rule-based untuk ekstraksi data transaksi dari transkrip speech-to-text.
 * Normalisasi teks -> pemetaan kata-angka -> pencocokan nama produk (exact + Levenshtein)
 * -> ekstraksi jumlah per produk (dengan satuan) -> nama pelanggan & catatan.
 */
class SpeechParser
{
    private const FILLERS = [
        'eh', 'eeh', 'e', 'hm', 'hmm', 'mm', 'mmm', 'anu', 'nah', 'jadi',
        'saya', 'mau', 'beli', 'pesan', 'minta', 'tolong', 'untuk', 'buat',
        'ya', 'yah', 'dong', 'nih', 'bang', 'pak', 'mbak', 'mas', 'bu', 'kak',
        'om', 'gan', 'sis', 'master', 'wkwk', 'hehe', 'iya', 'yep', 'oke',
        'kasih', 'dulu', 'kan', 'deh', 'maaf', 'permisi', 'numpang', 'juga',
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
     * @param string $raw   transkrip asli dari browser
     * @param iterable $products koleksi {id,name,price}
     * @return array{raw:string,normalized:string,name:?string,items:array,note:?string,total_kg:float,total:int}
     */
    public function parse(string $raw, iterable $products): array
    {
        $tokens = $this->tokenize($raw);
        $norms = $this->normalizeTokens($tokens);

        $aliases = [];
        foreach ($products as $product) {
            $aliasTokens = array_map(fn ($t) => mb_strtolower($t), $this->tokenize((string) $product->name));
            $aliases[] = ['product' => $product, 'tokens' => $aliasTokens];
        }

        $matches = $this->findProducts($tokens, $norms, $aliases);
        $used = [];
        foreach ($matches as $m) {
            for ($i = $m['start']; $i <= $m['end']; $i++) {
                $used[$i] = true;
            }
        }

        $items = [];
        foreach ($matches as $idx => $m) {
            [$qty, $unit, $consumed] = $this->extractQty($norms, $m['start'], $m['end'], $used, $matches);
            foreach ($consumed as $ci) {
                $used[$ci] = true;
            }
            $product = $m['product'];
            $items[] = [
                'product_id' => (int) $product->id,
                'name' => (string) $product->name,
                'price' => (int) $product->price,
                'qty' => round($qty, 3),
                'unit' => $unit,
                'subtotal' => (int) round($qty * (int) $product->price),
                'confidence' => $m['confidence'],
            ];
        }

        $items = $this->mergeRepeatedProducts($items);

        $name = $this->extractCustomerName($tokens, $norms, $used, $matches);
        $note = $this->extractNote($tokens, $norms, $used, $matches);

        $normalized = implode(' ', array_values(array_filter($norms, fn ($n) => $n !== '')));

        return [
            'raw' => $raw,
            'normalized' => $normalized,
            'name' => $name,
            'items' => $items,
            'note' => $note,
            'total_kg' => round(array_sum(array_column($items, 'qty')), 3),
            'total' => (int) array_sum(array_column($items, 'subtotal')),
        ];
    }

    /** Potong kalimat menjadi token kata/angka (memisahkan "25kg" jadi "25","kg"). */
    public function tokenize(string $text): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?|\p{L}+/u', $text, $m);
        return $m[0];
    }

    /**
     * Ubah tiap token menjadi bentuk normal:
     * - angka digit -> nilai numerik ("2,5" -> "2.5", "1.000" -> "1000")
     * - deret kata angka -> nilai numerik ("dua puluh lima" -> "25")
     * - kata lain -> huruf kecil + buang imbuhan "nya"
     */
    private function normalizeTokens(array $tokens): array
    {
        $norms = [];
        $n = count($tokens);
        $i = 0;
        while ($i < $n) {
            $w = $tokens[$i];
            $lower = mb_strtolower($w);

            if (preg_match('/^\d+([.,]\d+)?$/', $w)) {
                $value = $this->numericValue($w);
                $next = $i + 1;
                // deret kata angka setelah digit: "2 setengah" -> 2.5, "2 koma lima" -> 2.5
                if ($next < $n && $this->isNumberWord(mb_strtolower($tokens[$next]))) {
                    [$value, $next] = $this->consumeNumberWords($tokens, $next, $n, $value);
                }
                for ($j = $i; $j < $next; $j++) {
                    $norms[$j] = '';
                }
                $norms[$next - 1] = $this->numStr((float) $value);
                $i = $next;
                continue;
            }

            if ($this->isNumberWord($lower)) {
                [$value, $next] = $this->consumeNumberWords($tokens, $i, $n);
                for ($j = $i; $j < $next; $j++) {
                    $norms[$j] = '';
                }
                $norms[$next - 1] = $this->numStr($value);
                $i = $next;
                continue;
            }

            $norms[$i] = $this->stem($lower);
            $i++;
        }

        return $this->mergeHalfValues($norms);
    }

    /**
     * Sisa "setengah" yang terpisah dari angkanya:
     * "2 setengah" -> 2.5, "dua kilo setengah" -> 2.5 (angka, lalu satuan).
     */
    private function mergeHalfValues(array $norms): array
    {
        foreach (array_keys($norms) as $i) {
            if ($norms[$i] === '' || !is_numeric($norms[$i]) || abs((float) $norms[$i] - 0.5) > 1e-9) {
                continue;
            }
            $prev = $i - 1;
            if ($prev >= 0 && $norms[$prev] !== '' && is_numeric($norms[$prev])) {
                $norms[$prev] = $this->numStr((float) $norms[$prev] + 0.5);
                $norms[$i] = '';
                continue;
            }
            if ($prev >= 1 && $norms[$prev] !== '' && $this->unitFactor(mb_strtolower($norms[$prev])) !== null
                && $norms[$prev - 1] !== '' && is_numeric($norms[$prev - 1])) {
                $norms[$prev - 1] = $this->numStr((float) $norms[$prev - 1] + 0.5);
                $norms[$i] = '';
            }
        }

        return $norms;
    }

    private function numericValue(string $token): string
    {
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $token)) {
            return (string) (int) str_replace('.', '', $token);
        }
        return (string) (float) str_replace(',', '.', $token);
    }

    private function numStr(float $value): string
    {
        return (string) $value;
    }

    private function isNumberWord(string $word): bool
    {
        $stem = $this->stem(mb_strtolower($word));
        return isset(self::DIGITS[$stem]) || in_array($stem, self::SCALES, true);
    }

    /**
     * Konsumsi deret kata angka ("dua puluh lima koma lima") mulai dari posisi $i.
     * $initial = nilai awal dari digit sebelumnya ("2" + "setengah" -> 2.5).
     *
     * @return array{0:float,1:int}
     */
    private function consumeNumberWords(array $tokens, int $i, int $n, ?float $initial = null): array
    {
        $result = 0;
        $current = $initial ?? 0;
        $sawScale = false;
        $decStr = null;
        $started = $initial !== null;
        $end = $i;

        while ($end < $n) {
            $raw = mb_strtolower($tokens[$end]);
            $w = $this->stem($raw);

            if ($w === 'koma') {
                if ($decStr === null) {
                    $decStr = '';
                    $started = true;
                    $end++;
                    continue;
                }
                break;
            }

            if ($decStr !== null) {
                if (isset(self::DIGITS[$w])) {
                    $decStr .= self::DIGITS[$w];
                    $end++;
                    continue;
                }
                if (preg_match('/^\d+$/', $raw)) {
                    $decStr .= $raw;
                    $end++;
                    continue;
                }
                break;
            }

            if (isset(self::DIGITS[$w])) {
                $d = self::DIGITS[$w];
                if ($sawScale) {
                    $result += $current;
                    $current = $d;
                    $sawScale = false;
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
                    $sawScale = true;
                    break;
                case 'sebelas':
                    $current = ($current ?: 1) * 10 + 1;
                    $sawScale = true;
                    break;
                case 'puluh':
                    $current = ($current ?: 1) * 10;
                    $sawScale = true;
                    break;
                case 'ratus':
                case 'seratus':
                    $current = ($current ?: 1) * 100;
                    $sawScale = true;
                    break;
                case 'ribu':
                case 'seribu':
                    $result += ($current ?: 1) * 1000;
                    $current = 0;
                    $sawScale = false;
                    break;
                case 'belas':
                    if ($current <= 0) {
                        return $started ? [($result + $current), max($end, $i + 1)] : [0.0, $i];
                    }
                    $current += 10;
                    break;
                case 'setengah':
                    $current += 0.5;
                    break;
                default:
                    return $started ? [($result + $current), max($end, $i + 1)] : [0.0, $i];
            }

            $started = true;
            $end++;
        }

        if (!$started) {
            return [0.0, $i];
        }

        $value = $result + $current;
        if ($decStr !== null && $decStr !== '') {
            $value = (float) ((string) $value . '.' . $decStr);
        }

        return [$value, max($end, $i + 1)];
    }

    private function stem(string $word): string
    {
        foreach (['nya', 'ku', 'mu', 'lah', 'kah', 'pun'] as $suffix) {
            if (mb_strlen($word) > strlen($suffix) + 2 && str_ends_with($word, $suffix)) {
                return mb_substr($word, 0, -strlen($suffix));
            }
        }
        return $word;
    }

    /**
     * Cari penyebutan nama produk pada deret token.
     * Pass 1: exact + fuzzy ketat (Levenshtein terbatas).
     * Pass 2: longgar untuk token yang belum terpakai (transkrip noise).
     */
    private function findProducts(array $tokens, array $norms, array $aliases): array
    {
        $n = count($tokens);
        $candidates = [];

        foreach ($aliases as $alias) {
            $aliasTokens = $alias['tokens'];
            $len = count($aliasTokens);
            if ($len === 0 || $len > $n) {
                continue;
            }
            for ($i = 0; $i + $len <= $n; $i++) {
                $confidence = $this->matchQuality($norms, $i, $aliasTokens);
                if ($confidence !== null) {
                    $candidates[] = [
                        'start' => $i,
                        'end' => $i + $len - 1,
                        'product' => $alias['product'],
                        'confidence' => $confidence,
                    ];
                }
            }
        }

        $selected = $this->greedySelect($candidates);

        // pass longgar: hanya token yang belum terpakai oleh pass ketat
        $covered = array_fill(0, $n, false);
        foreach ($selected as $m) {
            for ($i = $m['start']; $i <= $m['end']; $i++) {
                $covered[$i] = true;
            }
        }

        $looseCandidates = [];
        foreach ($aliases as $alias) {
            $aliasTokens = $alias['tokens'];
            $len = count($aliasTokens);
            if ($len === 0 || $len > $n) {
                continue;
            }
            for ($i = 0; $i + $len <= $n; $i++) {
                $blocked = false;
                for ($k = 0; $k < $len; $k++) {
                    if ($covered[$i + $k] || $this->skipLoose($norms[$i + $k] ?? '')) {
                        $blocked = true;
                        break;
                    }
                }
                if ($blocked) {
                    continue;
                }
                if ($this->looseMatchQuality($norms, $i, $aliasTokens)) {
                    $looseCandidates[] = [
                        'start' => $i,
                        'end' => $i + $len - 1,
                        'product' => $alias['product'],
                        'confidence' => 'loose',
                    ];
                }
            }
        }

        $selected = array_merge($selected, $this->greedySelect($looseCandidates));
        usort($selected, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $selected;
    }

    private function greedySelect(array $candidates): array
    {
        usort($candidates, function ($a, $b) {
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

        $selected = [];
        $lastEnd = -1;
        foreach ($candidates as $c) {
            if ($c['start'] > $lastEnd) {
                $selected[] = $c;
                $lastEnd = $c['end'];
            }
        }

        return $selected;
    }

    /** token yang tidak boleh dipakai pass longgar: angka & satuan */
    private function skipLoose(string $norm): bool
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
    private function matchQuality(array $norms, int $offset, array $aliasTokens): ?string
    {
        $exact = true;
        foreach ($aliasTokens as $k => $aliasTok) {
            $val = $norms[$offset + $k] ?? '';
            if ($val === $aliasTok) {
                continue;
            }
            if ($val !== '' && is_numeric($val) && is_numeric($aliasTok) && (float) $val === (float) $aliasTok) {
                continue;
            }
            if ($val === '' || $this->editDistance($val, $aliasTok) > $this->maxEdits(mb_strlen($aliasTok))) {
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
    private function looseMatchQuality(array $norms, int $offset, array $aliasTokens): bool
    {
        foreach ($aliasTokens as $k => $aliasTok) {
            $val = $norms[$offset + $k] ?? '';
            $aliasLen = mb_strlen($aliasTok);
            $valLen = mb_strlen($val);

            if ($aliasLen < 4 || $valLen < 4) {
                return false;
            }
            if ($val === $aliasTok) {
                continue;
            }
            if ($val !== '' && is_numeric($val) && is_numeric($aliasTok) && (float) $val === (float) $aliasTok) {
                continue;
            }
            $dist = $this->editDistance($val, $aliasTok);
            if ($dist > (int) floor(max($aliasLen, $valLen) / 2)) {
                return false;
            }
        }

        return true;
    }

    /** Produk sama disebut lebih dari sekali: nilai terakhir menang. */
    private function mergeRepeatedProducts(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $pid = $item['product_id'];
            if (isset($merged[$pid])) {
                $merged[$pid]['qty'] = $item['qty'];
                $merged[$pid]['unit'] = $item['unit'];
                $merged[$pid]['subtotal'] = $item['subtotal'];
                $merged[$pid]['confidence'] = $item['confidence'];
                continue;
            }
            $merged[$pid] = $item;
        }

        return array_values($merged);
    }

    private function maxEdits(int $len): int
    {
        if ($len <= 3) {
            return 0;
        }
        if ($len <= 5) {
            return 1;
        }
        return 2;
    }

    private function editDistance(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }
        $dist = levenshtein($a, $b);
        return $dist === false ? PHP_INT_MAX : $dist;
    }

    /**
     * Ambil jumlah untuk satu penyebutan produk:
     * (a) angka+satuan di kiri, (b) angka+satuan di kanan,
     * (c) angka apa pun di kiri, (d) angka apa pun di kanan, (e) default 1.
     *
     * @return array{0:float,1:?string,2:array}
     */
    private function extractQty(array $norms, int $start, int $end, array $used, array $matches): array
    {
        $left = [];
        $boundL = 0;
        foreach ($matches as $m) {
            if ($m['end'] < $start) {
                $boundL = max($boundL, $m['end'] + 1);
            }
        }
        for ($i = $start - 1; $i >= $boundL && $i >= 0; $i--) {
            $left[] = ['i' => $i, 'norm' => $norms[$i]];
        }

        $right = [];
        $boundR = count($norms);
        foreach ($matches as $m) {
            if ($m['start'] > $end) {
                $boundR = min($boundR, $m['start']);
            }
        }
        for ($i = $end + 1; $i < $boundR; $i++) {
            $right[] = ['i' => $i, 'norm' => $norms[$i]];
        }

        foreach ([$left, $right] as $window) {
            $hit = $this->qtyWithUnit($window, $used);
            if ($hit !== null) {
                return $hit;
            }
        }
        foreach ([$left, $right] as $window) {
            $hit = $this->qtyAnyNumber($window, $used);
            if ($hit !== null) {
                return $hit;
            }
        }

        return [1.0, null, []];
    }

    private function qtyWithUnit(array $window, array $used): ?array
    {
        foreach ($window as $idx => $entry) {
            $unit = $this->unitFactor(mb_strtolower($entry['norm']));
            if ($unit === null) {
                continue;
            }
            foreach ([$idx - 1, $idx + 1] as $nIdx) {
                if (!isset($window[$nIdx])) {
                    continue;
                }
                $nei = $window[$nIdx];
                if (isset($used[$nei['i']]) || !is_numeric($nei['norm']) || $nei['norm'] === '') {
                    continue;
                }
                return [(float) $nei['norm'] * $unit['factor'], $unit['label'], [$entry['i'], $nei['i']]];
            }
        }

        return null;
    }

    private function qtyAnyNumber(array $window, array $used): ?array
    {
        foreach ($window as $entry) {
            if (isset($used[$entry['i']])) {
                continue;
            }
            if ($entry['norm'] !== '' && is_numeric($entry['norm'])) {
                $value = (float) $entry['norm'];
                if ($value <= 0) {
                    continue;
                }
                return [$value, 'kg', [$entry['i']]];
            }
        }

        return null;
    }

    private function unitFactor(string $word): ?array
    {
        if (!isset(self::UNITS[$word])) {
            return null;
        }
        return ['factor' => self::UNITS[$word], 'label' => $word];
    }

    private function extractCustomerName(array $tokens, array $norms, array $used, array $matches): ?string
    {
        $first = $matches[0]['start'] ?? count($tokens);
        $parts = [];
        for ($i = 0; $i < $first; $i++) {
            if (isset($used[$i])) {
                continue;
            }
            $lower = mb_strtolower($tokens[$i]);
            if (in_array($lower, self::FILLERS, true)) {
                continue;
            }
            if (($norms[$i] ?? '') === '' || is_numeric($norms[$i])) {
                continue;
            }
            $parts[] = $tokens[$i];
            if (count($parts) >= 6) {
                break;
            }
        }

        $name = trim(implode(' ', $parts));
        return $name === '' ? null : $name;
    }

    private function extractNote(array $tokens, array $norms, array $used, array $matches): ?string
    {
        if (empty($matches)) {
            return null;
        }
        $first = $matches[0]['start'];
        $last = $matches[count($matches) - 1]['end'];
        $parts = [];
        for ($i = $first + 1; $i < count($tokens); $i++) {
            if ($i <= $last || isset($used[$i])) {
                continue;
            }
            $lower = mb_strtolower($tokens[$i]);
            if (in_array($lower, self::FILLERS, true)) {
                continue;
            }
            if (($norms[$i] ?? '') === '') {
                continue;
            }
            $parts[] = $tokens[$i];
        }

        $note = trim(implode(' ', $parts));
        return $note === '' ? null : $note;
    }
}
