<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('refuses to deploy an unconfigured administrator', function () {
    config(['app.bootstrap_admin' => ['name' => null, 'email' => null, 'password_hash' => null]]);
    $this->artisan('app:bootstrap-production-admin')->assertFailed();
    $this->assertDatabaseCount('users', 0);
});

it('initializes the existing administrator hash once and closes public setup', function () {
    $hash = Hash::make('TestOnlyPassword2026!');
    config(['app.bootstrap_admin' => ['name' => 'Deployment Admin', 'email' => 'deployment@example.test', 'password_hash' => $hash]]);
    $this->artisan('app:bootstrap-production-admin')->assertSuccessful();
    expect(User::firstOrFail()->password)->toBe($hash);
    expect(User::firstOrFail()->is_admin)->toBeTrue();
    $this->get(route('setup'))->assertForbidden();
    config(['app.bootstrap_admin.password_hash' => Hash::make('OtherTestPassword2026!')]);
    $this->artisan('app:bootstrap-production-admin')->assertSuccessful();
    $this->assertDatabaseCount('users', 1);
    expect(User::firstOrFail()->password)->toBe($hash);
});

it('never promotes an existing ordinary user through deployment bootstrap', function () {
    $user = User::factory()->create(['email' => 'deployment@example.test', 'is_admin' => false]);
    config(['app.bootstrap_admin' => ['name' => 'Deployment Admin', 'email' => $user->email, 'password_hash' => Hash::make('TestOnlyPassword2026!')]]);
    expect(fn () => $this->artisan('app:bootstrap-production-admin'))->toThrow(RuntimeException::class, 'already assigned');
    expect($user->fresh()->is_admin)->toBeFalse();
});
