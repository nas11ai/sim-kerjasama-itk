<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 — kolom identitas yang dituntut BR-IAM-02, BR-IAM-03, BR-IAM-06,
 * BR-IAM-12, dan BR-IAM-13.
 *
 * `user_profiles` sebelumnya hanya menyimpan tautan (user, role, organisasi):
 * tidak ada NIDN untuk diverifikasi, tidak ada status verifikasi, dan tidak
 * ada data tambahan yang menentukan kelengkapan profil.
 *
 * NIDN unik **atas nilai yang terisi** (BR-IAM-02): jalur undangan tidak
 * meminta NIDN, dan sejumlah akun tanpa NIDN tidak boleh saling bentrok.
 * PostgreSQL memperlakukan NULL sebagai tidak sama dengan NULL pada index
 * unik, jadi index biasa sudah memberi tepat perilaku itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('nidn', 32)->nullable()->unique()->after('user_id');
            $table->string('verification_status', 16)->default('pending')->after('nidn');
            $table->text('rejection_reason')->nullable()->after('verification_status');
            $table->string('phone', 32)->nullable()->after('rejection_reason');
            $table->string('expertise')->nullable()->after('phone');
            $table->string('photo_path')->nullable()->after('expertise');
        });

        Schema::table('invitation_tokens', function (Blueprint $table) {
            // BR-IAM-06 — sebab keempat token tidak berlaku, di samping kuota
            // habis, kedaluwarsa, dan organisasinya nonaktif/terhapus. Tanpa
            // ini tautan yang bocor tidak punya cara dimatikan selain menunggu
            // kedaluwarsa.
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
        });

        // Undangan yang pernah diterbitkan adalah jejak siapa diundang ke mana.
        // Foreign key RESTRICT membuat organisasi yang punya token tidak bisa
        // dihapus sama sekali — bertentangan dengan register #18, yang justru
        // menetapkan bahwa token **tidak** menghalangi penghapusan. Diubah
        // menjadi nullOnDelete: organisasinya lepas, tokennya bertahan sebagai
        // riwayat, dan `isValid()` menolaknya karena organisasinya tiada.
        Schema::table('invitation_tokens', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        Schema::table('invitation_tokens', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->change();
        });

        Schema::table('invitation_tokens', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'nidn',
                'verification_status',
                'rejection_reason',
                'phone',
                'expertise',
                'photo_path',
            ]);
        });

        Schema::table('invitation_tokens', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('revoked_at');
        });

        Schema::table('invitation_tokens', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations');
        });
    }
};
