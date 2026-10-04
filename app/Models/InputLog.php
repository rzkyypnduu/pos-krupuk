<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InputLog extends Model
{
    protected $fillable = [
        'method', 'tab', 'device', 'raw_text', 'normalized_text',
        'parsed_json', 'final_json', 'match_json',
        'duration_ms', 'started_at', 'sale_id', 'status', 'error',
    ];

    protected $casts = [
        'parsed_json' => 'array',
        'final_json' => 'array',
        'match_json' => 'array',
        'started_at' => 'datetime',
        'duration_ms' => 'integer',
    ];
}
