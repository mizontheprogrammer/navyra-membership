<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MembershipLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MembershipTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_number' => 'TXN-'.Str::ulid(), 'member_id' => Member::factory(), 'membership_level_id' => fn () => MembershipLevel::firstOrFail()->id,
            'created_by' => User::factory(), 'type' => 'Application', 'status' => 'Pending', 'amount' => '100.00', 'validity_days' => 30,
            'member_name' => fake()->name(), 'membership_number' => 'MEM-'.Str::ulid(), 'category_name' => 'Student', 'level_name' => 'Basic',
        ];
    }
}
