<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['date', 'amount', 'note'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'integer',
    ];
}
