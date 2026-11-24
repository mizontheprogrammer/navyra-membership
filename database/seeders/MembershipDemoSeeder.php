<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use App\Services\MembershipProcessor;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipDemoSeeder extends Seeder
{
    public function run(MembershipProcessor $processor): void
    {
        DB::transaction(function () use ($processor) {
            $admin = User::where('is_admin', true)->orderBy('id')->lockForUpdate()->firstOrFail();
            $examples = [
                ['001', '[DEMO] Alex Reyes', 'Student', 'Basic', 'Approved'],
                ['002', '[DEMO] Bea Santos', 'Regular', 'Silver', 'Paid'],
                ['003', '[DEMO] Carlo Cruz', 'Senior Citizen', 'Gold', 'Partial'],
                ['004', '[DEMO] Delta Company', 'Corporate', 'Platinum', 'Pending'],
                ['005', '[DEMO] Elena Garcia', 'Regular', 'Basic', 'Cancelled'],
                ['006', '[DEMO] Felix Mendoza', 'Student', 'Silver', 'Expired'],
            ];

            foreach ($examples as [$number, $name, $categoryName, $levelName, $status]) {
                $membershipNumber = 'DEMO-MEM-'.$number;
                if (Member::where('membership_number', $membershipNumber)->exists()) {
                    continue;
                }

                $category = MembershipCategory::where('name', $categoryName)->firstOrFail();
                $level = MembershipLevel::where('name', $levelName)->firstOrFail();
                $member = Member::create([
                    'membership_number' => $membershipNumber,
                    'name' => $name,
                    'membership_category_id' => $category->id,
                    'membership_level_id' => $level->id,
                    'company_name' => $categoryName === 'Corporate' ? '[DEMO] Delta Corporation' : null,
                    'email' => null,
                    'phone' => null,
                    'active' => false,
                ]);
                $details = [
                    'member_id' => $member->id,
                    'membership_level_id' => $level->id,
                    'type' => 'Application',
                    'notes' => 'DEMONSTRATION ONLY. Synthetic membership example; no real money received.',
                ];
                $transaction = $processor->create($details, $admin->id);

                if (in_array($status, ['Paid', 'Approved', 'Expired', 'Partial'], true)) {
                    $amount = $status === 'Partial' ? Money::decimal(intdiv(Money::cents($transaction->amount), 2)) : $transaction->amount;
                    $paidOn = $status === 'Expired' ? today()->subDays(400) : today();
                    if ($status === 'Expired') {
                        $transaction->update(['created_at' => $paidOn]);
                    }
                    $processor->pay($transaction, [
                        'amount' => $amount,
                        'method' => 'Cash',
                        'external_reference' => 'DEMO-PAY-'.$number,
                        'submission_key' => (string) Str::uuid(),
                        'paid_on' => $paidOn->toDateString(),
                        'notes' => 'DEMONSTRATION ONLY. Simulated payment; no money was collected.',
                    ], $admin->id);
                }

                if (in_array($status, ['Approved', 'Expired'], true)) {
                    $processor->changeStatus($transaction, 'approve', $admin->id);
                }
                if ($status === 'Approved') {
                    $processor->create([...$details, 'type' => 'Renewal'], $admin->id);
                }
                if ($status === 'Expired') {
                    $start = today()->subDays(400);
                    $end = $start->copy()->addDays($transaction->validity_days - 1);
                    $transaction->update(['status' => 'Expired', 'starts_on' => $start, 'ends_on' => $end, 'approved_at' => $start]);
                    $member->refresh()->forceFill(['active' => false, 'joined_on' => $start, 'expires_on' => $end])->save();
                }
                if ($status === 'Cancelled') {
                    $processor->changeStatus($transaction, 'cancel', $admin->id);
                }
            }
        });
    }
}
