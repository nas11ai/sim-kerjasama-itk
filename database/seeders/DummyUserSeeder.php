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
        $superadmin = User::firstOrCreate([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('superadmin@example.com'),
            'email_verified_at' => now(),
        ]);
        $superadmin->syncRoles('Super Admin');

        // Admin
        $admin = User::firstOrCreate([
            'name' => 'Admin Kampus',
            'email' => 'admin@example.com',
            'password' => bcrypt('admin@example.com'),
            'email_verified_at' => now(),
        ]);
        $admin->syncRoles('Admin');

        // Tendik / Reviewer
        $tendik = User::firstOrCreate([
            'name' => 'Reviewer Kampus',
            'email' => 'tendik@example.com',
            'password' => bcrypt('tendik@example.com'),
            'email_verified_at' => now(),
        ]);
        $tendik->syncRoles('Tenaga Kependidikan');

        // Mahasiswa
        $mahasiswa = User::firstOrCreate([
            'name' => 'Mahasiswa Dummy',
            'email' => 'mahasiswa@example.com',
            'password' => bcrypt('mahasiswa@example.com'),
            'email_verified_at' => now(),
        ]);
        $mahasiswa->syncRoles('Mahasiswa');
    }
}
