<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalLedger extends Model
{
    protected $fillable = ['date', 'name', 'amount', 'note'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'integer',
    ];
}
