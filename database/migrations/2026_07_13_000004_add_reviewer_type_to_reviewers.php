<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reviewers', function (Blueprint $table) {
            $table->string('reviewer_type')->nullable()->after('user_id');
        });
        // Cursor for lazy collection, without it can;t use each()
        DB::table('reviewers')->cursor()->each(function ($reviewer) {
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
            $table->foreignId('reviewer_role_id')
                ->nullable()
                ->after('user_id');
        });

        $internalRoleId = DB::table('reviewer_roles')
            ->where('name', 'Internal')
            ->value('id');

        $externalRoleId = DB::table('reviewer_roles')
            ->where('name', 'External')
            ->value('id');

        DB::table('reviewers')
            ->where('reviewer_type', 'internal')
            ->update([
                'reviewer_role_id' => $internalRoleId,
            ]);

        DB::table('reviewers')
            ->where('reviewer_type', 'external')
            ->update([
                'reviewer_role_id' => $externalRoleId,
            ]);

        Schema::table('reviewers', function (Blueprint $table) {
              $table->foreignId('reviewer_role_id')
                ->nullable(false)
                ->change();
                
            $table->foreign('reviewer_role_id')
                ->references('id')
                ->on('reviewer_roles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->dropColumn('reviewer_type');
        });
    }
};
