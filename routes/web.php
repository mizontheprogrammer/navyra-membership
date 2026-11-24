<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MembershipApplicationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WeatherController;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\RefreshMembershipExpiry;
use Illuminate\Support\Facades\Route;

Route::get('/membership/apply', [MembershipApplicationController::class, 'create'])->name('membership.create');
Route::post('/membership/apply', [MembershipApplicationController::class, 'store'])->middleware('throttle:membership-applications')->name('membership.store');
Route::get('/membership/verify/{application}/{hash}', [MembershipApplicationController::class, 'verify'])->middleware(['signed', 'throttle:membership-confirmation'])->name('membership.verify');
Route::post('/membership/verify/{application}/{hash}', [MembershipApplicationController::class, 'confirm'])->middleware(['signed', 'throttle:membership-confirmation'])->name('membership.confirm');
Route::get('/weather', [WeatherController::class, 'show'])->middleware('throttle:membership-weather')->name('weather.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/setup', [AuthController::class, 'setup'])->name('setup');
    Route::post('/setup', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('setup.store');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth', EnsureAdministrator::class, RefreshMembershipExpiry::class])->group(function () {
    Route::get('/applications', [MembershipApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [MembershipApplicationController::class, 'show'])->name('applications.show');
    Route::patch('/applications/{application}/review', [MembershipApplicationController::class, 'review'])->name('applications.review');
    Route::post('/applications/{application}/resend', [MembershipApplicationController::class, 'resend'])->middleware('throttle:membership-resend')->name('applications.resend');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/about', [PageController::class, 'about']);
    Route::get('/tasks/{id}', [PageController::class, 'task']);
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/members/{member}/history', [MemberController::class, 'history'])->name('members.history');
    Route::get('/members/{member}/record', [MemberController::class, 'record'])->name('members.record');
    Route::get('/members/{member}/status/confirm', [MemberController::class, 'confirmStatus'])->name('members.status.confirm');
    Route::patch('/members/{member}/status', [MemberController::class, 'status'])->name('members.status');
    Route::resource('members', MemberController::class);
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('levels', LevelController::class)->except('show');
    Route::get('/membership-levels', fn () => redirect()->route('levels.index'))->name('membership-levels.index');
    Route::resource('transactions', TransactionController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('/transactions/{transaction}/status', [TransactionController::class, 'status'])->name('transactions.status');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::patch('/payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
});
