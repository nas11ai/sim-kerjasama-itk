<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviewers', function (Blueprint $table) {
            $table->string('reviewer_type')->nullable()->after('user_id');
        });

        DB::table('reviewers')->each(function ($reviewer) {
            $role = DB::table('reviewer_roles')->find($reviewer->reviewer_role_id);
            $type = str_contains(strtolower($role?->name ?? ''), 'external') ? 'external' : 'internal';
            DB::table('reviewers')->where('id', $reviewer->id)->update(['reviewer_type' => $type]);
        });

        Schema::table('reviewers', function (Blueprint $table) {
            $table->string('reviewer_type')->nullable(false)->change();
            $table->dropForeign(['reviewer_role_id']);
            $table->dropColumn('reviewer_role_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviewers', function (Blueprint $table) {
            $table->foreignId('reviewer_role_id')->nullable()->constrained()->onUpdate('cascade')->onDelete('cascade');
        });

        DB::statement("UPDATE reviewers SET reviewer_role_id = (SELECT id FROM reviewer_roles WHERE name = 'Internal') WHERE reviewer_type = 'internal'");

        Schema::table('reviewers', function (Blueprint $table) {
            $table->dropColumn('reviewer_type');
        });
    }
};
