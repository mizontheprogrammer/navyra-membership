<?php

use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use App\Notifications\MembershipApplicationSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

function publicApplicationData(array $overrides = []): array
{
    return array_replace([
        'membership_category_id' => MembershipCategory::firstOrFail()->id,
        'full_name' => 'Ana Client', 'email' => 'ana-client@example.test',
        'phone' => '09123456789', 'address' => 'Manila', 'latitude' => '14.5995000', 'longitude' => '120.9842000',
        'consent' => '1', 'submission_key' => (string) Str::uuid(),
    ], $overrides);
}

it('shows the public form with active categories and never exposes the weather secret', function () {
    config(['services.google_maps.key' => 'browser-maps-key', 'services.openweather.key' => 'server-secret-test']);
    MembershipCategory::first()->update(['is_active' => false]);
    $this->get(route('membership.create'))->assertOk()->assertSee('browser-maps-key')->assertDontSee('server-secret-test')->assertSee('14.5995')->assertSee('Create membership application');
    $this->get(route('applications.index'))->assertRedirect(route('login'));
});

it('stores the application and sends an after-commit queued confirmation notification', function () {
    $category = MembershipCategory::first();
    $category->update(['fee' => '250.50', 'level' => 'Associate']);
    $this->post(route('membership.store'), publicApplicationData())->assertRedirect(route('membership.create'))->assertSessionHas('success');
    $application = MembershipApplication::firstOrFail();
    expect($application->reference_no)->toStartWith('APP-');
    expect($application->status)->toBe('pending');
    expect($application->latitude)->toBe('14.5995000');
    expect($application->category_fee)->toBe('250.50');
    expect($application->consented_at)->not->toBeNull();
    expect($application->verification_sent_at)->toBeNull();
    Notification::assertSentOnDemand(MembershipApplicationSubmitted::class, function ($notification, $channels, $notifiable) {
        return $notification instanceof ShouldQueue && $notification->afterCommit === true && $notification->queue === 'membership-mail' && $notifiable->routes['mail'] === 'ana-client@example.test';
    });
    $this->assertDatabaseCount('members', 0);
});

it('validates inactive categories coordinates email and consent and blocks the honeypot', function () {
    $category = MembershipCategory::first();
    $category->update(['is_active' => false]);
    $this->post(route('membership.store'), publicApplicationData(['email' => 'invalid', 'latitude' => '91', 'longitude' => '-181', 'consent' => '0', 'website' => 'spam']))
        ->assertSessionHasErrors(['membership_category_id', 'email', 'latitude', 'longitude', 'consent', 'website']);
    $this->assertDatabaseCount('membership_applications', 0);
    Notification::assertNothingSent();
});

it('requires a complete coordinate pair but allows no coordinates', function () {
    $this->post(route('membership.store'), publicApplicationData(['longitude' => '']))->assertSessionHasErrors('longitude');
    $this->post(route('membership.store'), publicApplicationData(['latitude' => '', 'longitude' => '']))->assertSessionHasNoErrors();
});

it('does not create duplicate applications or emails on repeat submission', function () {
    $data = publicApplicationData();
    $this->post(route('membership.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('membership.store'), $data)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('membership_applications', 1);
    Notification::assertSentOnDemandTimes(MembershipApplicationSubmitted::class, 1);
    $this->post(route('membership.store'), array_replace($data, ['email' => 'other@example.test']))->assertSessionHasErrors('full_name');
});

it('requires an authentic unexpired signed link and an explicit confirmation click', function () {
    $application = MembershipApplication::factory()->create();
    $url = URL::temporarySignedRoute('membership.verify', now()->addHours(48), ['application' => $application->id, 'hash' => sha1($application->email)]);
    $this->get($url)->assertOk()->assertSee('Confirm my email');
    expect($application->fresh()->email_verified_at)->toBeNull();
    $this->post($url)->assertOk()->assertSee('Email confirmed');
    expect($application->fresh()->email_verified_at)->not->toBeNull();
    $this->post($url)->assertOk();
    $this->get('/membership/verify/'.$application->id.'/'.sha1($application->email))->assertForbidden();
    $this->travel(49)->hours();
    $this->get($url)->assertForbidden();
});

it('only marks SMTP delivery when a mail notification is sent', function () {
    $application = MembershipApplication::factory()->create();
    $notification = new MembershipApplicationSubmitted($application);
    event(new NotificationSent(new AnonymousNotifiable, $notification, 'mail', null));
    expect($application->fresh()->verification_sent_at)->not->toBeNull();
    $message = $notification->toMail(new AnonymousNotifiable);
    expect($message->subject)->toBe('Membership application received');
    expect($message->actionUrl)->toContain('/membership/verify/');
});

it('protects the admin application pages from ordinary users', function () {
    $application = MembershipApplication::factory()->create();
    $this->actingAs(User::factory()->create())->get(route('applications.show', $application))->assertForbidden();
});

it('registers a confirmed applicant and creates exactly one pending membership transaction', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $application = MembershipApplication::factory()->create(['email_verified_at' => now()]);
    $level = MembershipLevel::first();
    $level->update(['fee' => '500.00', 'validity_days' => 365]);
    $this->get(route('applications.index'))->assertOk();
    $this->get(route('applications.show', $application))->assertOk();
    $this->patch(route('applications.review', $application), ['action' => 'register', 'membership_level_id' => $level->id])->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe('registered');
    expect($application->fresh()->member->active)->toBeFalse();
    expect($application->fresh()->transaction->status)->toBe('Pending');
    expect($application->fresh()->transaction->amount)->toBe('500.00');
    $this->patch(route('applications.review', $application), ['action' => 'register', 'membership_level_id' => $level->id])->assertSessionHasErrors('application');
    $this->assertDatabaseCount('members', 1);
    $this->assertDatabaseCount('membership_transactions', 1);
});

it('blocks unconfirmed registration and rolls back when a tier has no configured fee', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $application = MembershipApplication::factory()->create();
    $level = MembershipLevel::first();
    $level->update(['fee' => null]);
    $this->patch(route('applications.review', $application), ['action' => 'register', 'membership_level_id' => $level->id])->assertSessionHasErrors('application');
    $application->update(['email_verified_at' => now()]);
    $this->patch(route('applications.review', $application), ['action' => 'register', 'membership_level_id' => $level->id])->assertSessionHasErrors('membership_level_id');
    $this->assertDatabaseCount('members', 0);
    expect($application->fresh()->status)->toBe('pending');
});

it('supports rejection and controlled resending without confirming an email', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $application = MembershipApplication::factory()->create(['verification_queued_at' => now()->subMinutes(2)]);
    $this->post(route('applications.resend', $application))->assertSessionHasNoErrors();
    expect($application->fresh()->email_verified_at)->toBeNull();
    $this->post(route('applications.resend', $application))->assertSessionHasErrors('email');
    $this->patch(route('applications.review', $application), ['action' => 'reject', 'review_notes' => 'Details incomplete'])->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe('rejected');
    $this->post(route('applications.resend', $application))->assertSessionHasErrors('email');
});

it('protects used categories and updates public category settings', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $category = MembershipCategory::first();
    MembershipApplication::factory()->create(['membership_category_id' => $category->id]);
    $this->delete(route('categories.destroy', $category))->assertSessionHasErrors('catalog');
    $this->put(route('categories.update', $category), ['name' => $category->name, 'fee' => '100.00', 'level' => 'Standard', 'is_active' => '0'])->assertSessionHasNoErrors();
    expect($category->fresh()->is_active)->toBeFalse();
    $this->get(route('categories.edit', $category))->assertOk()->assertSee('Public application availability');
});

it('keeps the original consent time when email and review status change', function () {
    $application = MembershipApplication::factory()->create(['consented_at' => now()->subDays(2)]);
    $original = $application->consented_at->toDateTimeString();
    event(new NotificationSent(new AnonymousNotifiable, new MembershipApplicationSubmitted($application), 'mail', null));
    expect($application->fresh()->consented_at->toDateTimeString())->toBe($original);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->patch(route('applications.review', $application), ['action' => 'reject'])->assertSessionHasNoErrors();
    expect($application->fresh()->consented_at->toDateTimeString())->toBe($original);
});
