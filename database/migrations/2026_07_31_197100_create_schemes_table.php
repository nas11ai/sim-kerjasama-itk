<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_type_id')->constrained();
            $table->foreignId('submission_type_id')->constrained();
            $table->string('name');
            $table->string('code')->unique();
            $table->bigInteger('max_budget');
            $table->integer('max_members');
            $table->integer('duration_months');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schemes');
    }
};
