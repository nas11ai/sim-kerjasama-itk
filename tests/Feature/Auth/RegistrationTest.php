<?php

use App\Models\StudyProgram;
use App\Models\Faculty;
use Spatie\Permission\Models\Role;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');
    $response->assertStatus(200);
});

test('new users can register', function () {
    $role = Role::create(['name' => 'mahasiswa', 'guard_name' => 'web']);

    $faculty = Faculty::create(['name' => 'Fakultas Test']);
    $studyProgram = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Test Program'
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => $role->id,
        'study_program' => $studyProgram->id,
    ]);

    $response->assertRedirect(route('login'));
});
