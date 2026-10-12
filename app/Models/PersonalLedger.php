<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalLedger extends Model
{
    protected $fillable = ['date', 'name', 'amount'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'integer',
    ];
}
