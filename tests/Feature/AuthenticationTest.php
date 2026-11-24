<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);
it('protects all system pages from guests', function () {
    foreach (['dashboard', 'members.index', 'categories.index', 'levels.index', 'transactions.index', 'payments.index', 'tasks.index'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
});
it('creates the first administrator and disables repeated setup', function () {
    $this->get(route('login'))->assertRedirect(route('setup'));
    $this->get(route('setup'))->assertOk();
    $this->post(route('setup.store'), ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'Testing1234', 'password_confirmation' => 'Testing1234'])->assertRedirect(route('dashboard'));
    expect(User::first()->is_admin)->toBeTrue();
    expect(Hash::check('Testing1234', User::first()->password))->toBeTrue();
    $this->assertAuthenticated();
    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->get(route('setup'))->assertForbidden();
    $this->post(route('setup.store'), ['name' => 'Other', 'email' => 'other@example.com', 'password' => 'Testing1234', 'password_confirmation' => 'Testing1234'])->assertForbidden();
    $this->assertDatabaseCount('users', 1);
});
it('uses administrator credentials to log in and rejects ordinary users', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('Testing1234')]);
    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'Testing1234'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($admin);
    $this->post(route('logout'));
    $this->assertGuest();
    $ordinary = User::factory()->create(['password' => Hash::make('Testing1234')]);
    $this->post(route('login.store'), ['email' => $ordinary->email, 'password' => 'Testing1234'])->assertSessionHasErrors('email');
    $this->actingAs($ordinary)->get(route('members.index'))->assertForbidden();
});
it('validates administrator passwords and throttles login attempts', function () {
    $this->post(route('setup.store'), ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), ['email' => 'none@example.com', 'password' => 'wrong']);
    }
    $this->post(route('login.store'), ['email' => 'none@example.com', 'password' => 'wrong'])->assertTooManyRequests();
});
