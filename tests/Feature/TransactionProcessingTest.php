<?php

use App\Models\Member;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\User;
use App\Services\MembershipProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
    $this->member = Member::factory()->create();
    $this->level = MembershipLevel::firstOrFail();
    $this->level->update(['fee' => '100.00', 'validity_days' => 30, 'benefits' => 'Original benefits']);
    $this->processor = app(MembershipProcessor::class);
});

function applicationFor($test, array $overrides = []): MembershipTransaction
{
    return $test->processor->create(array_replace(['member_id' => $test->member->id, 'membership_level_id' => $test->level->id, 'type' => 'Application'], $overrides), $test->admin->id);
}

function paymentData(string $amount = '100.00', array $overrides = []): array
{
    return array_replace(['amount' => $amount, 'method' => 'Cash', 'paid_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid()], $overrides);
}

it('creates a pending application with immutable fee and identity snapshots', function () {
    $this->post(route('transactions.store'), ['member_id' => $this->member->id, 'membership_level_id' => $this->level->id, 'type' => 'Application'])->assertSessionHasNoErrors()->assertRedirect();
    $transaction = MembershipTransaction::firstOrFail();
    expect($transaction->reference_number)->toStartWith('TXN-');
    expect($transaction->status)->toBe('Pending');
    $this->level->update(['fee' => '250.00', 'benefits' => 'New benefits']);
    $this->member->update(['name' => 'Changed Name']);
    expect($transaction->fresh()->amount)->toBe('100.00');
    expect($transaction->fresh()->benefits)->toBe('Original benefits');
    expect($transaction->member_name)->not->toBe('Changed Name');
});

it('rejects another open transaction and renewal before application approval', function () {
    $this->post(route('transactions.store'), ['member_id' => $this->member->id, 'membership_level_id' => $this->level->id, 'type' => 'Renewal'])->assertSessionHasErrors('type');
    applicationFor($this);
    $this->post(route('transactions.store'), ['member_id' => $this->member->id, 'membership_level_id' => $this->level->id, 'type' => 'Application'])->assertSessionHasErrors('member_id');
    $this->assertDatabaseCount('membership_transactions', 1);
});

it('requires a configured fee and supports explicitly free memberships', function () {
    $this->level->update(['fee' => null]);
    $this->post(route('transactions.store'), ['member_id' => $this->member->id, 'membership_level_id' => $this->level->id, 'type' => 'Application'])->assertSessionHasErrors('membership_level_id');
    $this->level->update(['fee' => '0.00']);
    $transaction = applicationFor($this);
    $this->patch(route('transactions.status', $transaction), ['action' => 'approve'])->assertSessionHasErrors('status');
    $this->patch(route('transactions.status', $transaction), ['action' => 'mark-free-paid'])->assertSessionHasNoErrors();
    $this->patch(route('transactions.status', $transaction), ['action' => 'approve'])->assertSessionHasNoErrors();
    expect($transaction->fresh()->status)->toBe('Approved');
    $this->assertDatabaseCount('payments', 0);
});

it('records partial and full payments and only then allows approval', function () {
    $transaction = applicationFor($this);
    $this->post(route('payments.store'), ['membership_transaction_id' => $transaction->id, ...paymentData('40.25')])->assertSessionHasNoErrors()->assertRedirect();
    expect($transaction->fresh()->status)->toBe('Pending');
    expect($transaction->balanceCents())->toBe(5975);
    $this->patch(route('transactions.status', $transaction), ['action' => 'approve'])->assertSessionHasErrors('status');
    $this->post(route('payments.store'), ['membership_transaction_id' => $transaction->id, ...paymentData('59.75')])->assertSessionHasNoErrors();
    expect($transaction->fresh()->status)->toBe('Paid');
    $this->patch(route('transactions.status', $transaction), ['action' => 'approve'])->assertSessionHasNoErrors();
    expect($transaction->fresh()->status)->toBe('Approved');
    expect($this->member->fresh()->joined_on->toDateString())->toBe(today()->toDateString());
    expect($this->member->fresh()->expires_on->toDateString())->toBe(today()->addDays(29)->toDateString());
    $this->get(route('members.history', $this->member))->assertOk()->assertSee($transaction->reference_number);
    $this->get(route('transactions.show', $transaction))->assertOk()->assertSee('40.25')->assertSee('59.75');
});

it('rejects overpayments negative amounts precision errors and missing external references', function () {
    $transaction = applicationFor($this);
    foreach (['101.00', '-1', '0', '2.123'] as $amount) {
        $this->post(route('payments.store'), ['membership_transaction_id' => $transaction->id, ...paymentData($amount)])->assertSessionHasErrors('amount');
    }
    $this->post(route('payments.store'), ['membership_transaction_id' => $transaction->id, ...paymentData('10', ['method' => 'GCash'])])->assertSessionHasErrors('external_reference');
    $this->post(route('payments.store'), ['membership_transaction_id' => 999, ...paymentData('10')])->assertSessionHasErrors('membership_transaction_id');
    $this->post(route('payments.store'), ['membership_transaction_id' => $transaction->id, ...paymentData('10', ['paid_on' => today()->addDay()->toDateString()])])->assertSessionHasErrors('paid_on');
    $this->assertDatabaseCount('payments', 0);
});

it('does not duplicate a payment when the same form is submitted again', function () {
    $transaction = applicationFor($this);
    $data = ['membership_transaction_id' => $transaction->id, ...paymentData('25.00')];
    $this->post(route('payments.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('payments.store'), $data)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('payments', 1);
    expect($transaction->balanceCents())->toBe(7500);
});

it('prevents payments on cancelled and fully paid transactions', function () {
    $transaction = applicationFor($this);
    $this->patch(route('transactions.status', $transaction), ['action' => 'cancel'])->assertSessionHasNoErrors();
    $this->post(route('payments.store'), ['membership_transaction_id' => $transaction->id, ...paymentData('1')])->assertSessionHasErrors('amount');
    $new = applicationFor($this);
    $this->processor->pay($new, paymentData(), $this->admin->id);
    $this->post(route('payments.store'), ['membership_transaction_id' => $new->id, ...paymentData('1')])->assertSessionHasErrors('amount');
    expect($transaction->reference_number)->not->toBe($new->reference_number);
});

it('does not allow cancellation after receiving a partial payment', function () {
    $transaction = applicationFor($this);
    $this->processor->pay($transaction, paymentData('1'), $this->admin->id);
    $this->patch(route('transactions.status', $transaction), ['action' => 'cancel'])->assertSessionHasErrors('status');
    expect($transaction->fresh()->status)->toBe('Pending');
});

it('extends an early renewal from the existing expiry without approving twice', function () {
    $transaction = applicationFor($this);
    $this->processor->pay($transaction, paymentData(), $this->admin->id);
    $this->processor->changeStatus($transaction, 'approve', $this->admin->id);
    $expiry = $this->member->fresh()->expires_on->copy();
    $renewal = $this->processor->create(['member_id' => $this->member->id, 'membership_level_id' => $this->level->id, 'type' => 'Renewal'], $this->admin->id);
    $this->processor->pay($renewal, paymentData(), $this->admin->id);
    $this->patch(route('transactions.status', $renewal), ['action' => 'approve'])->assertSessionHasNoErrors();
    expect($renewal->fresh()->starts_on->toDateString())->toBe($expiry->copy()->addDay()->toDateString());
    expect($this->member->fresh()->expires_on->toDateString())->toBe($expiry->copy()->addDays(30)->toDateString());
    $this->patch(route('transactions.status', $renewal), ['action' => 'approve'])->assertSessionHasErrors('status');
});

it('expires approved periods and renews expired membership from today', function () {
    $transaction = applicationFor($this);
    $this->processor->pay($transaction, paymentData(), $this->admin->id);
    $this->processor->changeStatus($transaction, 'approve', $this->admin->id);
    $this->travel(35)->days();
    $this->get(route('dashboard'))->assertOk();
    expect($transaction->fresh()->status)->toBe('Expired');
    expect($this->member->fresh()->active)->toBeFalse();
    $renewal = $this->processor->create(['member_id' => $this->member->id, 'membership_level_id' => $this->level->id, 'type' => 'Renewal'], $this->admin->id);
    $this->processor->pay($renewal, paymentData(), $this->admin->id);
    $this->processor->changeStatus($renewal, 'approve', $this->admin->id);
    expect($this->member->fresh()->active)->toBeTrue();
    expect($renewal->fresh()->starts_on->toDateString())->toBe(today()->toDateString());
});

it('renders dashboard totals printable receipts lists and forms', function () {
    $transaction = applicationFor($this);
    $payment = $this->processor->pay($transaction, paymentData(), $this->admin->id);
    $this->get(route('dashboard'))->assertOk()->assertViewHas('collectedPayments', '100.00')->assertViewHas('totalMembers', 1);
    foreach (['transactions.index', 'transactions.create', 'payments.index', 'payments.create', 'categories.index', 'categories.create', 'levels.index', 'levels.create'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('payments.receipt', $payment))->assertOk()->assertSee($payment->receipt_number)->assertSee($transaction->reference_number)->assertSee('100.00');
});
