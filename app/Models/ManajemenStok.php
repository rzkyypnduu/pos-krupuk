<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManajemenStok extends Model
{
    protected $table = 'manajemen_stok';

    protected $fillable = ['tanggal', 'nama', 'jumlah', 'harga', 'kelompok'];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'float',
        'harga' => 'integer',
        'kelompok' => 'array',
    ];

    public function subtotal(): float
    {
        $total = 0;
        foreach ($this->kelompok ?? [] as $kelompok) {
            $karung = $kelompok['sacks'] ?? [];
            $jumlahKelompok = array_sum($karung);
            $hargaKelompok = $kelompok['price'] ?? $this->harga ?? 0;
            $total += $jumlahKelompok * $hargaKelompok;
        }

        return $total ?: $this->jumlah * $this->harga;
    }

    public static function totalJumlah(array $daftarKelompok): float
    {
        $total = 0;
        foreach ($daftarKelompok as $kelompok) {
            $total += array_sum($kelompok['sacks'] ?? []);
        }

        return $total;
    }
}
