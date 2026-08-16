<?php

namespace Database\Factories;

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResearchMember> */
class ResearchMemberFactory extends Factory
{
    protected $model = ResearchMember::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'form_submission_id' => FormSubmission::factory(),
            'user_profile_id' => UserProfile::factory(),
            'role' => ResearchMember::ROLE_MEMBER,
            'status' => 'active',
        ];
    }

    public function coInvestigator(): static
    {
        return $this->state(fn () => ['role' => ResearchMember::ROLE_CO_INVESTIGATOR]);
    }
}
