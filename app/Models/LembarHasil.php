<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LembarHasil extends Model
{
    protected $table = 'lembar_hasil';

    protected $fillable = ['tanggal', 'tanggal_sumber'];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_sumber' => 'date',
    ];
}
