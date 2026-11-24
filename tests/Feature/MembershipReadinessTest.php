<?php

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use App\Services\MembershipProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);
it('configures annual level prices and retains older transaction snapshots', function () {
    foreach (['Basic' => '200.00', 'Silver' => '400.00', 'Gold' => '600.00', 'Platinum' => '1000.00'] as $name => $fee) {
        $level = MembershipLevel::where('name', $name)->firstOrFail();
        expect($level->fee)->toBe($fee);
        expect($level->validity_days)->toBe(365);
        expect($level->benefits)->not->toBeEmpty();
    }
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $member = Member::factory()->create();
    $transaction = app(MembershipProcessor::class)->create(['member_id' => $member->id, 'membership_level_id' => MembershipLevel::where('name', 'Basic')->firstOrFail()->id, 'type' => 'Application'], $admin->id);
    $transaction->membershipLevel->update(['fee' => '250.00', 'validity_days' => 90]);
    expect($transaction->fresh()->amount)->toBe('200.00');
    expect($transaction->fresh()->validity_days)->toBe(365);
});
it('shows a confirmation without deactivating until explicitly confirmed', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $member = Member::factory()->create(['active' => true]);
    $this->get(route('members.status.confirm', [$member, 'active' => 0]))->assertOk()->assertSee('Confirm Deactivation')->assertSee('Cancel');
    expect($member->fresh()->active)->toBeTrue();
    $this->patch(route('members.status', $member), ['active' => 0])->assertSessionHasErrors('confirm_deactivation');
    expect($member->fresh()->active)->toBeTrue();
    $this->patch(route('members.status', $member), ['active' => 0, 'confirm_deactivation' => 1])->assertSessionHasNoErrors();
    expect($member->fresh()->active)->toBeFalse();
    $this->patch(route('members.status', $member), ['active' => 1])->assertSessionHasNoErrors();
    expect($member->fresh()->active)->toBeTrue();
});
it('protects a historical category after reassignment and renaming', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $member = Member::factory()->create();
    $original = $member->membershipCategory;
    $transaction = app(MembershipProcessor::class)->create(['member_id' => $member->id, 'membership_level_id' => $member->membership_level_id, 'type' => 'Application'], $admin->id);
    $new = MembershipCategory::where('id', '!=', $original->id)->firstOrFail();
    $member->update(['membership_category_id' => $new->id]);
    $original->update(['name' => 'Renamed historic group']);
    $this->delete(route('categories.destroy', $original))->assertSessionHasErrors('catalog');
    $this->assertDatabaseHas('membership_categories', ['id' => $original->id]);
    expect($transaction->fresh()->membership_category_id)->toBe($original->id);
});
it('requires confirmation when deactivating from the edit form', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $member = Member::factory()->create(['active' => true]);
    $data = ['membership_number' => $member->membership_number, 'name' => $member->name, 'membership_category_id' => $member->membership_category_id, 'membership_level_id' => $member->membership_level_id, 'active' => 0];
    $this->put(route('members.update', $member), $data)->assertSessionHasErrors('confirm_deactivation');
    expect($member->fresh()->active)->toBeTrue();
    $this->put(route('members.update', $member), [...$data, 'confirm_deactivation' => 1])->assertSessionHasNoErrors();
    expect($member->fresh()->active)->toBeFalse();
});
