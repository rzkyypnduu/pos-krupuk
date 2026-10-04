<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SpeechParser;
use Illuminate\Http\Request;

/**
 * Speech-to-text untuk form Transaksi Baru: terima kandidat teks (N-best)
 * dari browser, pilih hasil parser terbaik, kirim balik untuk mengisi form.
 */
class SpeechController extends Controller
{
    public function parseSpeech(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:2000',
            'alternatives' => 'sometimes|array|max:5',
            'alternatives.*' => 'string|max:2000',
        ], [
            'text.required' => 'Teks suara kosong.',
        ]);

        $products = Product::orderBy('name')->get();
        $parser = new SpeechParser();

        // Browser mengirim beberapa kandidat hasil N-best; pilih yang paling masuk akal.
        $candidates = [$request->input('text', '')];
        foreach ((array) $request->input('alternatives', []) as $alt) {
            $alt = trim((string) $alt);
            if ($alt !== '' && !in_array($alt, $candidates, true)) {
                $candidates[] = $alt;
            }
        }

        $best = null;
        $bestScore = PHP_INT_MIN;
        foreach ($candidates as $candidate) {
            $parsed = $parser->parse($candidate, $products);
            $loose = count(array_filter($parsed['items'], fn ($i) => $i['confidence'] === 'loose'));
            $score = count($parsed['items']) * 10 + ($parsed['name'] !== null && $parsed['name'] !== '' ? 5 : 0) - $loose;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $parsed;
            }
        }

        return response()->json(['ok' => true, 'candidates' => count($candidates)] + $best);
    }
}
