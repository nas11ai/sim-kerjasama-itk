<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $column = $this->legacySchemeColumn();

            if (Schema::hasColumn('form_submissions', $column)) {
                $table->dropColumn($column);
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $column = $this->legacySchemeColumn();

            if (!Schema::hasColumn('form_submissions', $column)) {
                $table->unsignedBigInteger($column)->nullable();
            }
        });
    }

    private function legacySchemeColumn(): string
    {
        return 'scheme'.'_id';
    }
};
