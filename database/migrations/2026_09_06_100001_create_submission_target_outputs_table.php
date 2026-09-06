<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target luaran yang dijanjikan sebuah pengajuan (BR-SM-14, BR-SM-15).
 *
 * Bedakan dari `research_outputs` yang merekam luaran terealisasi: taksonomi
 * `output_type`-nya sama, tapi momennya berbeda — janji versus hasil. DDD
 * sengaja tidak menggabungkan keduanya, karena aturan siapa-boleh-mengubah
 * berbeda (BR-SM-15 melarang co-investigator, BR-RO-07 membolehkannya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_target_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->string('output_type');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('form_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_target_outputs');
    }
};
