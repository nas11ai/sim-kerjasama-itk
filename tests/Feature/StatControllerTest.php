<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Memastikan role Admin ada sebelum test dijalankan
    Role::firstOrCreate(['name' => 'Admin']);
});

test('admin can access reviewer stats endpoint without SQL error', function () {
    if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('Fungsi EXTRACT(YEAR) standar SQL tidak didukung di SQLite in-memory testing.');
    }

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this
        ->actingAs($user)
        ->get('/admin/stats/get-reviewers');

    $response->assertOk();
});
