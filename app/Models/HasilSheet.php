<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilSheet extends Model
{
    protected $fillable = ['date', 'source_date'];

    protected $casts = [
        'date' => 'date',
        'source_date' => 'date',
    ];
}
