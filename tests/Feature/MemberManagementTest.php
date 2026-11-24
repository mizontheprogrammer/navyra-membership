<?php

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});
function memberPayload(array $changes = []): array
{
    return array_replace(['name' => 'Ana Reyes', 'membership_category_id' => MembershipCategory::first()->id, 'membership_level_id' => MembershipLevel::first()->id, 'active' => '1', 'email' => 'ana@example.com'], $changes);
}
it('registers a member with a unique automatic membership number', function () {
    $this->post(route('members.store'), memberPayload())->assertSessionHasNoErrors()->assertRedirect();
    $first = Member::firstOrFail();
    expect($first->membership_number)->toStartWith('MEM-');
    $this->post(route('members.store'), memberPayload(['name' => 'Ben Cruz']))->assertRedirect();
    expect(Member::pluck('membership_number')->unique()->count())->toBe(2);
    $this->get(route('members.show', $first))->assertOk()->assertSee('Ana Reyes');
});
it('rejects duplicate numbers invalid email and missing assignments', function () {
    $member = Member::factory()->create(['membership_number' => 'MEM-ONE']);
    $this->post(route('members.store'), memberPayload(['membership_number' => 'MEM-ONE', 'email' => 'bad', 'membership_category_id' => 999, 'membership_level_id' => 999]))->assertSessionHasErrors(['membership_number', 'email', 'membership_category_id', 'membership_level_id']);
    $this->assertDatabaseCount('members', 1);
});
it('edits and deactivates a member and validates expired reactivation', function () {
    $member = Member::factory()->create();
    $this->put(route('members.update', $member), memberPayload(['membership_number' => $member->membership_number, 'name' => 'Updated Name']))->assertSessionHasNoErrors();
    $this->patch(route('members.status', $member), ['active' => 0, 'confirm_deactivation' => 1])->assertRedirect();
    expect($member->fresh()->active)->toBeFalse();
    $this->patch(route('members.status', $member), ['active' => 1])->assertRedirect();
    expect($member->fresh()->active)->toBeTrue();
    $member->forceFill(['expires_on' => today()->subDay()])->save();
    $this->patch(route('members.status', $member), ['active' => 1])->assertSessionHasErrors('active');
});
it('searches name category and level and combines filters', function () {
    $category = MembershipCategory::where('name', 'Corporate')->first();
    $level = MembershipLevel::where('name', 'Gold')->first();
    Member::factory()->create(['name' => 'Ana Reyes']);
    Member::factory()->create(['name' => 'Ben Cruz', 'membership_category_id' => $category->id, 'membership_level_id' => $level->id]);
    foreach (['Ben', 'ben', 'Corporate', 'corporate', 'Gold', 'gold'] as $search) {
        $this->get(route('members.index', ['search' => $search]))->assertOk()->assertSee('Ben Cruz')->assertDontSee('Ana Reyes');
    }
    $this->get(route('members.index', ['search' => 'Ana', 'category' => $category->id]))->assertOk()->assertDontSee('Ana Reyes')->assertSee('No matching members');
});
it('protects financial history when deleting or changing a membership number', function () {
    $member = Member::factory()->create();
    MembershipTransaction::factory()->create(['member_id' => $member->id]);
    $this->delete(route('members.destroy', $member))->assertSessionHasErrors('member');
    $this->assertDatabaseHas('members', ['id' => $member->id]);
    $this->put(route('members.update', $member), memberPayload(['membership_number' => 'CHANGED']))->assertSessionHasErrors('membership_level_id');
});
it('deletes a member without transactions and renders forms records and history', function () {
    $member = Member::factory()->create();
    foreach (['members.show', 'members.edit', 'members.record', 'members.history'] as $route) {
        $this->get(route($route, $member))->assertOk();
    }
    $this->get(route('members.create'))->assertOk();
    $this->delete(route('members.destroy', $member))->assertRedirect(route('members.index'));
    $this->get(route('members.show', $member))->assertNotFound();
});
