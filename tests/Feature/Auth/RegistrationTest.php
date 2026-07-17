<?php

use App\Models\Organization;
use Spatie\Permission\Models\Role;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');
    $response->assertStatus(200);
});

test('new users can register', function () {
    $role = Role::create(['name' => 'mahasiswa', 'guard_name' => 'web']);

    $faculty = Organization::create([
        'name' => 'Fakultas Test',
        'type' => 'faculty',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $studyProgram = Organization::create([
        'name' => 'Test Program',
        'type' => 'study_program',
        'parent_id' => $faculty->id,
        'is_active' => true,
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => $role->id,
        'study_program' => $studyProgram->id,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
