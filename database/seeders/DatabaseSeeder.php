<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DummyUserSeeder::class,
            FieldTypeSeeder::class,
            FormTypeSeeder::class,
            OrganizationSeeder::class,
            FacultySeeder::class,
            StudyProgramSeeder::class,
            PhaseTypeSeeder::class,
            SubmissionDateLabelSeeder::class,
            PermissionSeeder::class,
        ]);
    }
}
