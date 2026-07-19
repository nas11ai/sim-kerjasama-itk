<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * PermissionSeeder owns the Spatie catalog + DDD role matrix (#177/#178/#179).
     * OrganizationSeeder seeds the ITK org tree. Both must remain in this list.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            DummyUserSeeder::class,
            FieldTypeSeeder::class,
            FormTypeSeeder::class,
            FacultySeeder::class,
            StudyProgramSeeder::class,
            OrganizationSeeder::class,
            PhaseTypeSeeder::class,
            SubmissionDateLabelSeeder::class,
        ]);
    }
}
