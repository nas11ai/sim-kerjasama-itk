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
        Schema::create('form_phase_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_phase_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('form_access_control_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('phase_type_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->smallInteger('order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_phase_details');
    }
};
