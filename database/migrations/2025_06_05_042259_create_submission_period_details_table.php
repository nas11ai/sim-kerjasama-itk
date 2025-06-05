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
        Schema::create('submission_period_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_period_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('submission_rule_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_period_details');
    }
};
