<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('input_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 16); // speech | manual | fill (otomatis)
            $table->string('tab', 32)->nullable();
            $table->string('device')->nullable();
            $table->text('raw_text')->nullable();      // transkrip asli (speech)
            $table->text('normalized_text')->nullable(); // hasil normalisasi
            $table->json('parsed_json')->nullable();   // hasil ekstraksi SpeechParser
            $table->json('final_json')->nullable();    // nilai form saat disimpan (ground truth)
            $table->json('match_json')->nullable();    // perbandingan parsed vs final per field
            $table->unsignedBigInteger('duration_ms')->nullable(); // durasi input (mulai -> submit)
            $table->timestamp('started_at')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('status', 16); // ok | error
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['method', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('input_logs');
    }
};
