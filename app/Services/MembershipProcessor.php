<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Notifications\MembershipTransactionCreated;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipProcessor
{
    public function create(array $data, int $userId): MembershipTransaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $level = MembershipLevel::findOrFail($data['membership_level_id']);
            if ($member->transactions()->whereIn('status', ['Pending', 'Paid'])->exists()) {
                throw ValidationException::withMessages(['member_id' => 'This member already has an unfinished transaction.']);
            }
            $hasApplication = $member->transactions()->where('type', 'Application')->whereNotNull('approved_at')->exists();
            if ($data['type'] === 'Renewal' && ! $hasApplication) {
                throw ValidationException::withMessages(['type' => 'Approve an application before creating a renewal.']);
            }
            if ($data['type'] === 'Application' && $hasApplication) {
                throw ValidationException::withMessages(['type' => 'This member already has an approved application. Choose Renewal.']);
            }
            if ($level->fee === null) {
                throw ValidationException::withMessages(['membership_level_id' => 'Set the fee for this level first. Enter 0 for a free level.']);
            }

            $transaction = MembershipTransaction::create([
                'reference_number' => 'TXN-'.Str::ulid(), 'member_id' => $member->id, 'membership_level_id' => $level->id, 'created_by' => $userId,
                'type' => $data['type'], 'status' => 'Pending', 'amount' => $level->fee, 'validity_days' => $level->validity_days,
                'member_name' => $member->name, 'membership_number' => $member->membership_number, 'category_name' => $member->category, 'membership_category_id' => $member->membership_category_id,
                'level_name' => $level->name, 'benefits' => $level->benefits, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            if ($member->email) {
                Notification::route('mail', $member->email)->notify(new MembershipTransactionCreated($transaction));
            }

            return $transaction;
        }, 3);
    }

    public function pay(MembershipTransaction $transaction, array $data, int $userId): Payment
    {
        return DB::transaction(function () use ($transaction, $data, $userId) {
            $transaction = MembershipTransaction::lockForUpdate()->findOrFail($transaction->id);
            $existing = Payment::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                if ($existing->membership_transaction_id !== $transaction->id) {
                    throw ValidationException::withMessages(['amount' => 'This payment form has already been used. Open a new payment form.']);
                }
                if ($existing->amount !== Money::decimal(Money::cents($data['amount'])) || $existing->method !== $data['method'] || $existing->paid_on->toDateString() !== $data['paid_on'] || $existing->external_reference !== ($data['external_reference'] ?? null) || $existing->notes !== ($data['notes'] ?? null)) {
                    throw ValidationException::withMessages(['amount' => 'This payment form was already submitted with different details. Open a new form.']);
                }

                return $existing;
            }
            if ($transaction->status !== 'Pending') {
                throw ValidationException::withMessages(['amount' => 'Payments can only be recorded for pending transactions.']);
            }
            $cents = Money::cents($data['amount']);
            if ($cents <= 0 || $cents > $transaction->balanceCents()) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount no greater than the remaining balance.']);
            }
            $payment = $transaction->payments()->create([
                'receipt_number' => 'RCT-'.Str::ulid(), 'recorded_by' => $userId, 'submission_key' => $data['submission_key'],
                'amount' => Money::decimal($cents), 'method' => $data['method'], 'external_reference' => $data['external_reference'] ?? null,
                'paid_on' => $data['paid_on'], 'notes' => $data['notes'] ?? null,
            ]);
            if ($transaction->balanceCents() === 0) {
                $transaction->update(['status' => 'Paid']);
            }

            return $payment;
        }, 3);
    }

    public function changeStatus(MembershipTransaction $transaction, string $action, int $userId, ?string $reason = null): void
    {
        DB::transaction(function () use ($transaction, $action, $userId, $reason) {
            $member = Member::lockForUpdate()->findOrFail($transaction->member_id);
            $transaction = MembershipTransaction::lockForUpdate()->findOrFail($transaction->id);
            if ($action === 'reverse') {
                if (! in_array($transaction->status, ['Approved', 'Expired'], true) || ! $transaction->approved_at) {
                    throw ValidationException::withMessages(['status' => 'Only an approved or expired membership can have its approval reversed.']);
                }
                if (mb_strlen(trim($reason ?? '')) < 5) {
                    throw ValidationException::withMessages(['reason' => 'Enter a correction reason of at least 5 characters.']);
                }
                if ($member->transactions()->where('id', '>', $transaction->id)->where('status', '!=', 'Cancelled')->exists()) {
                    throw ValidationException::withMessages(['status' => 'Correct later renewals first, then cancel those transactions before reversing this approval.']);
                }
                $transaction->corrections()->create(['action' => 'Approval reversed', 'reason' => trim($reason), 'performed_by' => $userId, 'previous_state' => $transaction->only(['status', 'approved_by', 'approved_at', 'starts_on', 'ends_on'])]);
                $transaction->update(['status' => 'Paid', 'approved_at' => null, 'approved_by' => null, 'starts_on' => null, 'ends_on' => null]);
                $prior = $member->transactions()->whereIn('status', ['Approved', 'Expired'])->whereNotNull('approved_at')->orderByDesc('id')->first();
                $first = $member->transactions()->whereIn('status', ['Approved', 'Expired'])->whereNotNull('approved_at')->orderBy('id')->first();
                $member->forceFill(['active' => $prior && (! $prior->ends_on || $prior->ends_on->gte(today())), 'membership_level_id' => $prior?->membership_level_id ?? $member->membership_level_id, 'joined_on' => $first?->starts_on, 'expires_on' => $prior?->ends_on])->save();

                return;
            }
            if ($action === 'cancel') {
                if ($transaction->status !== 'Pending' || $transaction->payments()->whereNull('voided_at')->exists()) {
                    throw ValidationException::withMessages(['status' => 'Only unpaid pending transactions can be cancelled.']);
                }
                $transaction->update(['status' => 'Cancelled', 'cancelled_at' => now()]);

                return;
            }
            if ($action === 'mark-free-paid') {
                if ($transaction->status !== 'Pending' || Money::cents($transaction->amount) !== 0) {
                    throw ValidationException::withMessages(['status' => 'Only a pending zero-fee transaction can be marked paid without a payment.']);
                }
                $transaction->update(['status' => 'Paid']);

                return;
            }
            if ($action !== 'approve' || $transaction->status !== 'Paid' || $transaction->balanceCents() !== 0) {
                throw ValidationException::withMessages(['status' => 'Only a fully paid transaction can be approved.']);
            }
            $start = today();
            if ($transaction->type === 'Renewal' && $member->expires_on && $member->expires_on->gte(today())) {
                $start = $member->expires_on->copy()->addDay();
            }
            $end = $transaction->validity_days ? $start->copy()->addDays($transaction->validity_days - 1) : null;
            $transaction->update(['status' => 'Approved', 'approved_by' => $userId, 'approved_at' => now(), 'starts_on' => $start, 'ends_on' => $end]);
            $member->forceFill(['membership_level_id' => $transaction->membership_level_id, 'active' => true, 'joined_on' => $member->joined_on ?? $start, 'expires_on' => $end])->save();
        }, 3);
    }

    public function voidPayment(Payment $payment, string $reason, int $userId): void
    {
        DB::transaction(function () use ($payment, $reason, $userId) {
            $memberId = $payment->transaction->member_id;
            Member::lockForUpdate()->findOrFail($memberId);
            $transaction = MembershipTransaction::lockForUpdate()->findOrFail($payment->membership_transaction_id);
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->voided_at || ! in_array($transaction->status, ['Pending', 'Paid'], true)) {
                throw ValidationException::withMessages(['payment' => 'Reverse membership approval before voiding its payment. A voided payment cannot be voided again.']);
            }
            if (mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['reason' => 'Enter a correction reason of at least 5 characters.']);
            }
            $transaction->corrections()->create(['payment_id' => $payment->id, 'action' => 'Payment voided', 'reason' => trim($reason), 'performed_by' => $userId, 'previous_state' => ['receipt_number' => $payment->receipt_number, 'amount' => $payment->amount, 'transaction_status' => $transaction->status]]);
            $payment->update(['voided_at' => now(), 'voided_by' => $userId, 'void_reason' => trim($reason)]);
            $transaction->update(['status' => 'Pending']);
        }, 3);
    }

    public function expire(): void
    {
        MembershipTransaction::where('status', 'Approved')->whereDate('ends_on', '<', today())->update(['status' => 'Expired']);
        Member::where('active', true)->whereDate('expires_on', '<', today())->update(['active' => false]);
    }
}
