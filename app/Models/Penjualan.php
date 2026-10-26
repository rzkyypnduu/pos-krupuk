<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    protected $table = 'penjualan';

    protected $fillable = [
        'tanggal', 'nama', 'total_kotor', 'total_dibulatkan', 'dibayar',
        'dibayar_kemarin', 'kemarin_aktif', 'selisih', 'catatan',
        'tombol_bayar_diklik',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_kotor' => 'integer',
        'total_dibulatkan' => 'integer',
        'dibayar' => 'integer',
        'dibayar_kemarin' => 'integer',
        'kemarin_aktif' => 'boolean',
        'selisih' => 'integer',
        'tombol_bayar_diklik' => 'boolean',
    ];

    public function daftarItem()
    {
        return $this->hasMany(ItemPenjualan::class, 'penjualan_id');
    }
}
