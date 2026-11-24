<?php

namespace Database\Factories;

use App\Models\MembershipCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MembershipApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'membership_category_id' => fn () => MembershipCategory::firstOrFail()->id,
            'reference_no' => 'APP-'.Str::ulid(), 'submission_key' => (string) Str::uuid(),
            'full_name' => fake()->name(), 'email' => fake()->safeEmail(),
            'phone' => null, 'address' => 'Manila', 'latitude' => '14.5995000', 'longitude' => '120.9842000',
            'status' => 'pending', 'category_name' => 'Student', 'category_level' => null,
            'category_fee' => '0.00', 'consented_at' => now(), 'verification_queued_at' => now(),
        ];
    }
}
