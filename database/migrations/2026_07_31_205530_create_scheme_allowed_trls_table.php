<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scheme_allowed_trls', function (Blueprint $table) {
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trl_id')->constrained('technology_readiness_levels');
            $table->primary(['scheme_id', 'trl_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheme_allowed_trls');
    }
};
