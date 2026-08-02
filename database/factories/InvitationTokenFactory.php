<?php

namespace Database\Factories;

use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvitationToken>
 */
class InvitationTokenFactory extends Factory
{
    protected $model = InvitationToken::class;

    public function definition(): array
    {
        return [
            'token' => Str::random(64),
            'organization_id' => static fn () => Organization::create([
                'name' => fake()->company(),
                'type' => 'faculty',
                'is_active' => true,
            ])->id,
            'permissions' => ['users.manage'],
            'max_uses' => 5,
            'used_count' => 0,
            'expires_at' => now()->addDays(7),
            'created_by' => User::factory(),
        ];
    }
}
