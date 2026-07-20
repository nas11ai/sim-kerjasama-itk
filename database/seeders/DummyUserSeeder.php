<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DummyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Superadmin
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('superadmin@example.com'),
                'email_verified_at' => now(),
            ]
        );
        $superadmin->syncRoles('Super Admin');

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Kampus',
                'password' => bcrypt('admin@example.com'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles('Admin');

        // Tendik / Reviewer
        $tendik = User::firstOrCreate(
            ['email' => 'tendik@example.com'],
            [
                'name' => 'Reviewer Kampus',
                'password' => bcrypt('tendik@example.com'),
                'email_verified_at' => now(),
            ]
        );
        $tendik->syncRoles('Tenaga Kependidikan');

        // Mahasiswa
        $mahasiswa = User::firstOrCreate(
            ['email' => 'mahasiswa@example.com'],
            [
                'name' => 'Mahasiswa Dummy',
                'password' => bcrypt('mahasiswa@example.com'),
                'email_verified_at' => now(),
            ]
        );
        $mahasiswa->syncRoles('Mahasiswa');
    }
}
