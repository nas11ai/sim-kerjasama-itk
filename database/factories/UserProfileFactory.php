<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Organization tidak punya factory sendiri (model-nya tidak pakai
        // HasFactory), jadi dibuat langsung lewat create(), sama seperti pola
        // di FormPhaseDetailFactory dan AddRelationsToFormSubmissionsTest.
        $organization = Organization::create([
            'name' => 'Organisasi Uji '.fake()->unique()->numerify('####-####'),
            'type' => 'study_program',
        ]);

        return [
            'user_id' => User::factory(),
            'organization_id' => $organization->id,
            'role_id' => null,
        ];
    }
}
