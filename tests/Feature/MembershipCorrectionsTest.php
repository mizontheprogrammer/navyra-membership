<?php

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use App\Notifications\MembershipApplicationSubmitted;
use App\Notifications\MembershipTransactionCreated;
use App\Services\MembershipProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
function correctionSetup(): array
{
    $admin = User::factory()->create(['is_admin' => true]);
    $member = Member::factory()->create(['email' => 'qa@example.test']);
    $level = $member->membershipLevel;
    $level->update(['fee' => '100.00', 'validity_days' => 30]);
    $processor = app(MembershipProcessor::class);
    $transaction = $processor->create(['member_id' => $member->id, 'membership_level_id' => $level->id, 'type' => 'Application'], $admin->id);

    return [$admin, $member, $level, $processor, $transaction];
}
function correctionPayment(): array
{
    return ['amount' => '100.00', 'method' => 'Cash', 'paid_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid()];
}
it('reverses approval with audit history and voids payment without erasing receipt', function () {
    Notification::fake();
    $this->freezeTime();
    [$admin,$member,$level,$processor,$transaction] = correctionSetup();
    $this->actingAs($admin);
    $payment = $processor->pay($transaction, correctionPayment(), $admin->id);
    $processor->changeStatus($transaction, 'approve', $admin->id);
    $this->patch(route('payments.void', $payment), ['reason' => 'Wrong payment entered'])->assertSessionHasErrors('payment');
    $this->patch(route('transactions.status', $transaction), ['action' => 'reverse'])->assertSessionHasErrors('reason');
    $this->patch(route('transactions.status', $transaction), ['action' => 'reverse', 'reason' => 'Wrong member selected'])->assertSessionHasNoErrors();
    expect($transaction->fresh()->status)->toBe('Paid');
    expect($member->fresh()->active)->toBeFalse();
    expect($member->fresh()->joined_on)->toBeNull();
    $this->patch(route('payments.void', $payment), ['reason' => 'Wrong payment entered'])->assertSessionHasNoErrors();
    expect($payment->fresh()->voided_at)->not->toBeNull();
    expect($transaction->fresh()->status)->toBe('Pending');
    expect($transaction->paidCents())->toBe(0);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('membership_corrections', 2);
    $this->get(route('payments.receipt', $payment))->assertOk()->assertSee('VOIDED')->assertSee('Wrong payment entered');
    $this->get(route('transactions.show', $transaction))->assertOk()->assertSee('Wrong member selected')->assertSee('Wrong payment entered');
    $this->get(route('dashboard'))->assertViewHas('collectedPayments', 0);
    $this->patch(route('payments.void', $payment), ['reason' => 'Repeated attempt'])->assertSessionHasErrors('payment');
    $replacement = $processor->pay($transaction, correctionPayment(), $admin->id);
    expect($replacement->id)->not->toBe($payment->id);
    $this->patch(route('transactions.status', $transaction), ['action' => 'approve'])->assertSessionHasNoErrors();
    Notification::assertSentOnDemand(MembershipTransactionCreated::class);
});
it('protects earlier approval while dependent renewals exist and restores previous period', function () {
    Notification::fake();
    $this->freezeTime();
    [$admin,$member,$level,$processor,$original] = correctionSetup();
    $this->actingAs($admin);
    $processor->pay($original, correctionPayment(), $admin->id);
    $processor->changeStatus($original, 'approve', $admin->id);
    $expiry = $member->fresh()->expires_on->toDateString();
    $renewal = $processor->create(['member_id' => $member->id, 'membership_level_id' => $level->id, 'type' => 'Renewal'], $admin->id);
    $payment = $processor->pay($renewal, correctionPayment(), $admin->id);
    $processor->changeStatus($renewal, 'approve', $admin->id);
    $this->patch(route('transactions.status', $original), ['action' => 'reverse', 'reason' => 'Correct application'])->assertSessionHasErrors('status');
    $this->patch(route('transactions.status', $renewal), ['action' => 'reverse', 'reason' => 'Correct renewal'])->assertSessionHasNoErrors();
    expect($member->fresh()->expires_on->toDateString())->toBe($expiry);
    expect($member->fresh()->active)->toBeTrue();
    $this->patch(route('payments.void', $payment), ['reason' => 'Correct renewal payment'])->assertSessionHasNoErrors();
    $this->patch(route('transactions.status', $renewal), ['action' => 'cancel'])->assertSessionHasNoErrors();
    $this->patch(route('transactions.status', $original), ['action' => 'reverse', 'reason' => 'Correct application'])->assertSessionHasNoErrors();
    expect($member->fresh()->active)->toBeFalse();
});
it('requires corporate company name and stores it on member profiles', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $member = Member::factory()->create();
    $category = MembershipCategory::where('name', 'Corporate')->firstOrFail();
    $data = ['name' => 'Corporate Representative', 'membership_category_id' => $category->id, 'membership_level_id' => $member->membership_level_id, 'active' => 0];
    $this->post(route('members.store'), $data)->assertSessionHasErrors('company_name');
    $this->post(route('members.store'), [...$data, 'company_name' => 'Navyra Solutions Inc.'])->assertSessionHasNoErrors();
    $created = Member::where('name', 'Corporate Representative')->firstOrFail();
    $this->get(route('members.show', $created))->assertOk()->assertSee('Navyra Solutions Inc.');
});
it('validates staff transaction coordinates and saves valid locations', function () {
    Notification::fake();
    [$admin,$member,$level,$processor,$original] = correctionSetup();
    $this->actingAs($admin);
    $processor->changeStatus($original, 'cancel', $admin->id);
    $data = ['member_id' => $member->id, 'membership_level_id' => $level->id, 'type' => 'Application'];
    $this->post(route('transactions.store'), [...$data, 'latitude' => 91, 'longitude' => 120])->assertSessionHasErrors('latitude');
    $this->post(route('transactions.store'), [...$data, 'latitude' => 14])->assertSessionHasErrors('longitude');
    $this->post(route('transactions.store'), [...$data, 'latitude' => 14.5995, 'longitude' => 120.9842])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('membership_transactions', ['member_id' => $member->id, 'status' => 'Pending', 'latitude' => 14.5995, 'longitude' => 120.9842]);
});
it('denies correction actions to guests and non administrators', function () {
    Notification::fake();
    [$admin,$member,$level,$processor,$transaction] = correctionSetup();
    $payment = $processor->pay($transaction, correctionPayment(), $admin->id);
    $this->patch(route('payments.void', $payment), ['reason' => 'Wrong payment'])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_admin' => false]));
    $this->patch(route('payments.void', $payment), ['reason' => 'Wrong payment'])->assertForbidden();
    $this->patch(route('transactions.status', $transaction), ['action' => 'reverse', 'reason' => 'Wrong approval'])->assertForbidden();
    expect($payment->fresh()->voided_at)->toBeNull();
    $this->assertDatabaseCount('membership_corrections', 0);
});

it('requires company details on public Corporate applications and carries them into registration', function () {
    Notification::fake();
    $category = MembershipCategory::where('name', 'Corporate')->firstOrFail();
    $data = ['membership_category_id' => $category->id, 'full_name' => 'Corporate Applicant', 'email' => 'corporate@example.test', 'consent' => 1, 'submission_key' => (string) Str::uuid()];
    $this->post(route('membership.store'), $data)->assertSessionHasErrors('company_name');
    $this->post(route('membership.store'), [...$data, 'company_name' => 'Navyra Solutions Inc.'])->assertSessionHasNoErrors();
    $application = MembershipApplication::firstOrFail();
    $application->update(['email_verified_at' => now()]);
    $level = MembershipLevel::firstOrFail();
    $level->update(['fee' => '200.00']);
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->patch(route('applications.review', $application), ['action' => 'register', 'membership_level_id' => $level->id])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('members', ['company_name' => 'Navyra Solutions Inc.', 'email' => 'corporate@example.test']);
    Notification::assertSentOnDemand(MembershipApplicationSubmitted::class);
});
