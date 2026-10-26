<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PotonganSaldo extends Model
{
    protected $table = 'potongan_saldo';

    protected $fillable = ['tanggal', 'nilai_a', 'nilai_b'];

    protected $casts = [
        'tanggal' => 'date',
        'nilai_a' => 'integer',
        'nilai_b' => 'integer',
    ];

    public function hasil(): int
    {
        return ($this->nilai_a ?? 0) - ($this->nilai_b ?? 0);
    }
}
