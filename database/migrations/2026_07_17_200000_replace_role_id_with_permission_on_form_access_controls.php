<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const ROLE_TO_PERMISSION = [
        'researcher' => 'submissions.create',
        'Mahasiswa' => 'submissions.create',
        'reviewer_internal' => 'reviewers.evaluate',
        'reviewer_external' => 'reviewers.evaluate',
        'operator' => 'periods.manage',
        'Tenaga Kependidikan' => 'periods.manage',
        'admin' => 'submissions.view-all',
        'Admin' => 'submissions.view-all',
        'Super Admin' => 'submissions.view-all',
    ];

    public function up(): void
    {
        Schema::table('form_access_controls', function (Blueprint $table) {
            $table->string('permission')->nullable()->after('form_id');
        });

        $roleNames = DB::table('roles')->pluck('name', 'id');

        DB::table('form_access_controls')
            ->orderBy('id')
            ->chunkById(500, function ($controls) use ($roleNames) {
                foreach ($controls as $control) {
                    $roleName = $roleNames[$control->role_id] ?? null;
                    $permission = $roleName !== null
                        ? (self::ROLE_TO_PERMISSION[$roleName] ?? null)
                        : null;

                    if ($permission === null) {
                        throw new RuntimeException(
                            "Cannot map form_access_controls.id={$control->id} role_id={$control->role_id} (role={$roleName}) to permission."
                        );
                    }

                    DB::table('form_access_controls')
                        ->where('id', $control->id)
                        ->update(['permission' => $permission]);
                }
            });

        Schema::table('form_access_controls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        DB::statement('ALTER TABLE form_access_controls ALTER COLUMN permission SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('form_access_controls', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('form_id')
                ->constrained('roles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        $permissionToRoleId = [];
        foreach (self::ROLE_TO_PERMISSION as $roleName => $permission) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if ($roleId !== null && !isset($permissionToRoleId[$permission])) {
                $permissionToRoleId[$permission] = (int) $roleId;
            }
        }

        DB::table('form_access_controls')
            ->orderBy('id')
            ->chunkById(500, function ($controls) use ($permissionToRoleId) {
                foreach ($controls as $control) {
                    $roleId = $permissionToRoleId[$control->permission] ?? null;
                    if ($roleId === null) {
                        throw new RuntimeException(
                            "Cannot reverse-map form_access_controls.id={$control->id} permission={$control->permission}."
                        );
                    }

                    DB::table('form_access_controls')
                        ->where('id', $control->id)
                        ->update(['role_id' => $roleId]);
                }
            });

        Schema::table('form_access_controls', function (Blueprint $table) {
            $table->dropColumn('permission');
        });

        DB::statement('ALTER TABLE form_access_controls ALTER COLUMN role_id SET NOT NULL');
    }
};
