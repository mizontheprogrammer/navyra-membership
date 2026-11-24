<?php

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\MembershipDemoSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('adds repeatable labeled examples without modifying other records or sending email', function () {
    $this->freezeTime();
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $existing = Member::factory()->create(['name' => 'Existing member']);
    $application = MembershipApplication::factory()->create(['status' => 'pending']);

    $this->seed(MembershipDemoSeeder::class);
    $demo = Member::whereLike('membership_number', 'DEMO-MEM-%')->get();
    $alex = $demo->firstWhere('membership_number', 'DEMO-MEM-001');
    $alex->update(['phone' => '1234567']);
    $this->seed(MembershipDemoSeeder::class);

    $this->assertDatabaseCount('members', 7);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('membership_applications', 1);
    $this->assertDatabaseCount('membership_transactions', 7);
    $this->assertDatabaseCount('payments', 4);
    expect($existing->fresh()->name)->toBe('Existing member');
    expect($application->fresh()->status)->toBe('pending');
    expect($alex->fresh()->phone)->toBe('1234567');
    expect($demo)->toHaveCount(6);
    expect($demo->whereNotNull('email'))->toHaveCount(0);
    expect(Payment::sum('amount'))->toEqual('1300.00');
    foreach (['Pending' => 3, 'Paid' => 1, 'Approved' => 1, 'Expired' => 1, 'Cancelled' => 1] as $status => $count) {
        expect(MembershipTransaction::where('status', $status)->count())->toBe($count);
    }
    expect($alex->fresh()->active)->toBeTrue();
    expect($alex->transactions()->where('type', 'Renewal')->exists())->toBeTrue();
    $carlo = $demo->firstWhere('membership_number', 'DEMO-MEM-003');
    expect($carlo->transactions()->firstOrFail()->balanceCents())->toBe(30000);
    $expired = $demo->firstWhere('membership_number', 'DEMO-MEM-006')->fresh();
    expect($expired->active)->toBeFalse();
    expect($expired->expires_on->lt(today()))->toBeTrue();
    Notification::assertNothingSent();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertViewHas('demoMembers', 6)
        ->assertSee('Demo payments are not real collections.');
    $payment = Payment::where('external_reference', 'DEMO-PAY-001')->firstOrFail();
    $this->get(route('payments.receipt', $payment))->assertOk()
        ->assertSee('DEMONSTRATION ONLY. Simulated payment; no real money was collected.');
});

it('requires an existing administrator before adding demonstration data', function () {
    User::factory()->create(['is_admin' => false]);

    expect(fn () => $this->seed(MembershipDemoSeeder::class))->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('members', 0);
    $this->assertDatabaseCount('membership_transactions', 0);
    $this->assertDatabaseCount('payments', 0);
});

it('shows configured category reference fees on the public application', function () {
    foreach (['Student' => '200.00', 'Regular' => '400.00', 'Senior Citizen' => '200.00', 'Corporate' => '1000.00'] as $name => $fee) {
        expect(MembershipCategory::where('name', $name)->firstOrFail()->fee)->toBe($fee);
    }

    $this->get(route('membership.create'))->assertOk()
        ->assertSee('Student · PHP 200.00')
        ->assertSee('Regular · PHP 400.00')
        ->assertSee('Senior Citizen · PHP 200.00')
        ->assertSee('Corporate · PHP 1,000.00');
});

it('preserves custom category prices and submitted application fee snapshots', function () {
    $category = MembershipCategory::where('name', 'Student')->firstOrFail();
    $category->update(['fee' => '375.50']);
    $application = MembershipApplication::factory()->create(['membership_category_id' => $category->id, 'category_fee' => '0.00']);
    MembershipCategory::where('name', 'Regular')->update(['fee' => '0.00']);
    $migration = require database_path('migrations/2026_11_25_000842_configure_category_reference_fees.php');

    $migration->up();

    expect($category->fresh()->fee)->toBe('375.50');
    expect(MembershipCategory::where('name', 'Regular')->firstOrFail()->fee)->toBe('400.00');
    expect($application->fresh()->category_fee)->toBe('0.00');
});
