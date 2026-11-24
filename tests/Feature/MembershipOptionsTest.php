<?php

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});
it('seeds the four requested categories and levels and renders dropdowns', function () {
    expect(MembershipCategory::count())->toBe(4);
    expect(MembershipLevel::count())->toBe(4);
    $response = $this->get(route('members.create'))->assertOk();
    foreach (['Student', 'Regular', 'Senior Citizen', 'Corporate', 'Basic', 'Silver', 'Gold', 'Platinum'] as $name) {
        $response->assertSee($name);
    }
});
it('supports complete category management and protects assigned categories', function () {
    $this->post(route('categories.store'), ['name' => 'Alumni', 'description' => 'Graduates'])->assertSessionHasNoErrors();
    $category = MembershipCategory::where('name', 'Alumni')->firstOrFail();
    $this->get(route('categories.edit', $category))->assertOk();
    $this->put(route('categories.update', $category), ['name' => 'Alumni Club', 'description' => 'Updated'])->assertSessionHasNoErrors();
    $member = Member::factory()->create(['membership_category_id' => $category->id]);
    $this->delete(route('categories.destroy', $category))->assertSessionHasErrors('catalog');
    $member->delete();
    $this->delete(route('categories.destroy', $category))->assertRedirect();
    $this->assertDatabaseMissing('membership_categories', ['id' => $category->id]);
});
it('supports complete level management and validates money and validity', function () {
    $this->post(route('levels.store'), ['name' => 'Diamond', 'fee' => '250.50', 'validity_days' => 365, 'benefits' => 'Priority service'])->assertSessionHasNoErrors();
    $level = MembershipLevel::where('name', 'Diamond')->firstOrFail();
    $this->get(route('levels.edit', $level))->assertOk();
    $this->put(route('levels.update', $level), ['name' => 'Diamond', 'fee' => -1, 'validity_days' => 0])->assertSessionHasErrors(['fee', 'validity_days']);
    $this->put(route('levels.update', $level), ['name' => 'Diamond Plus', 'fee' => '0', 'validity_days' => '', 'benefits' => ''])->assertSessionHasNoErrors();
    expect($level->fresh()->fee)->toBe('0.00');
    expect($level->fresh()->validity_days)->toBeNull();
    $this->delete(route('levels.destroy', $level))->assertRedirect();
    $this->assertDatabaseMissing('membership_levels', ['id' => $level->id]);
});
it('protects referenced levels and rejects duplicate category names', function () {
    $level = MembershipLevel::first();
    MembershipTransaction::factory()->create(['membership_level_id' => $level->id]);
    $this->delete(route('levels.destroy', $level))->assertSessionHasErrors('catalog');
    $this->post(route('categories.store'), ['name' => 'Student'])->assertSessionHasErrors('name');
});
