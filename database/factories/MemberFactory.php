<?php

namespace Database\Factories;

use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(), 'membership_category_id' => fn () => MembershipCategory::firstOrFail()->id,
            'membership_level_id' => fn () => MembershipLevel::firstOrFail()->id, 'email' => fake()->safeEmail(), 'active' => true,
        ];
    }
}
