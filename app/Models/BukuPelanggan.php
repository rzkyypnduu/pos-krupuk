<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BukuPelanggan extends Model
{
    protected $table = 'buku_pelanggan';

    protected $fillable = [
        'tanggal', 'tanggal_lembar', 'nama', 'jumlah', 'jenis', 'catatan',
        'penjualan_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'integer',
    ];

    /**
     * Entri milik sebuah lembar hasil: tanggal itu + entri lama tanpa lembar;
     * null = semua entri. Menjadi satu-satunya rumus filter tanggal_lembar.
     */
    public function scopeUntukLembar($query, ?string $tanggalLembar)
    {
        if ($tanggalLembar === null) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('tanggal_lembar', $tanggalLembar)->orWhereNull('tanggal_lembar'));
    }
}
