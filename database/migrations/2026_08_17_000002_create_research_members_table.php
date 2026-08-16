<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_profile_id')->constrained('user_profiles')->cascadeOnDelete();
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestamps();

            // Satu profil hanya boleh sekali per submission.
            $table->unique(['form_submission_id', 'user_profile_id']);
        });

        DB::statement("
            ALTER TABLE research_members
            ADD CONSTRAINT research_members_role_check
            CHECK (role IN ('co_investigator', 'member'))
        ");

        DB::statement("
            ALTER TABLE research_members
            ADD CONSTRAINT research_members_status_check
            CHECK (status IN ('active', 'inactive'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('research_members');
    }
};
