<?php

namespace Database\Factories;

use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipCorrectionFactory extends Factory
{
    public function definition(): array
    {
        return ['membership_transaction_id' => MembershipTransaction::factory(), 'performed_by' => User::factory(), 'action' => 'Approval reversed', 'reason' => fake()->sentence(), 'previous_state' => ['status' => 'Approved']];
    }
}
