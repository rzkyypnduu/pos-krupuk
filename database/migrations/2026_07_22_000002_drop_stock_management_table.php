<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('stock_management');
    }

    public function down(): void
    {
        // Not recreating — the table was empty/unused
    }
};
