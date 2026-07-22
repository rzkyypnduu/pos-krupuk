<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaldoDeduction extends Model
{
    protected $fillable = ['date', 'a', 'b', 'note'];

    protected $casts = [
        'date' => 'date',
        'a' => 'integer',
        'b' => 'integer',
    ];

    public function result(): int
    {
        return ($this->a ?? 0) - ($this->b ?? 0);
    }
}
