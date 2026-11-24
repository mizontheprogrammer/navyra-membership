<?php

namespace Database\Factories;

use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'receipt_number' => 'RCT-'.Str::ulid(), 'membership_transaction_id' => MembershipTransaction::factory(),
            'recorded_by' => User::factory(), 'submission_key' => (string) Str::uuid(), 'amount' => '100.00', 'method' => 'Cash', 'paid_on' => today(),
        ];
    }
}
