<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMembershipApplicationRequest;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Notifications\MembershipApplicationSubmitted;
use App\Services\MembershipProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MembershipApplicationController extends Controller
{
    public function create(): View
    {
        return view('membership.create', ['categories' => MembershipCategory::where('is_active', true)->orderBy('name')->get(), 'submissionKey' => (string) Str::uuid()]);
    }

    public function store(StoreMembershipApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $application = DB::transaction(function () use ($data) {
            $category = MembershipCategory::where('is_active', true)->lockForUpdate()->findOrFail($data['membership_category_id']);
            $existing = MembershipApplication::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                if ($existing->email !== $data['email'] || $existing->full_name !== $data['full_name'] || (int) $existing->membership_category_id !== (int) $data['membership_category_id']) {
                    throw ValidationException::withMessages(['full_name' => 'This form was already submitted. Reload to start a new application.']);
                }

                return $existing;
            }
            $application = MembershipApplication::create([
                ...collect($data)->except(['consent', 'website'])->all(), 'reference_no' => 'APP-'.Str::ulid(),
                'category_name' => $category->name, 'category_level' => $category->level, 'category_fee' => $category->fee,
                'status' => 'pending', 'consented_at' => now(), 'verification_queued_at' => now(),
            ]);
            Notification::route('mail', $application->email)->notify(new MembershipApplicationSubmitted($application));

            return $application;
        });

        return redirect()->route('membership.create')->with('success', 'Application saved. Verification email queued. Reference: '.$application->reference_no);
    }

    public function verify(Request $request, MembershipApplication $application, string $hash): View
    {
        abort_unless(hash_equals(sha1($application->email), $hash), 403);

        return view('membership.verify', compact('application'));
    }

    public function confirm(Request $request, MembershipApplication $application, string $hash): View
    {
        abort_unless(hash_equals(sha1($application->email), $hash), 403);
        if (! $application->email_verified_at) {
            $application->update(['email_verified_at' => now()]);
        }

        return view('membership.verify', compact('application'));
    }

    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(['pending', 'registered', 'rejected'])]]);
        $applications = MembershipApplication::with('category')->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('reference_no', '%'.$s.'%')->orWhereLike('full_name', '%'.$s.'%')->orWhereLike('email', '%'.$s.'%')))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(15)->withQueryString();

        return view('applications', compact('applications'));
    }

    public function show(MembershipApplication $application): View
    {
        return view('application-detail', ['application' => $application->load(['category', 'member', 'transaction', 'reviewer']), 'levels' => MembershipLevel::orderBy('id')->get()]);
    }

    public function review(Request $request, MembershipApplication $application, MembershipProcessor $processor): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['register', 'reject'])], 'membership_level_id' => ['required_if:action,register', 'nullable', 'integer', 'exists:membership_levels,id'], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($application, $request, $processor, $data) {
            $application = MembershipApplication::lockForUpdate()->findOrFail($application->id);
            if ($application->status !== 'pending') {
                throw ValidationException::withMessages(['application' => 'This application has already been reviewed.']);
            }
            if ($data['action'] === 'reject') {
                $application->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_notes' => $data['review_notes'] ?? null]);

                return;
            }
            if (! $application->email_verified_at) {
                throw ValidationException::withMessages(['application' => 'The applicant must confirm their email before registration.']);
            }
            if (Member::where('email', $application->email)->exists()) {
                throw ValidationException::withMessages(['application' => 'A member already uses this email. Use the existing member profile to avoid duplicate registration.']);
            }
            $member = Member::create(['company_name' => $application->company_name, 'name' => $application->full_name, 'email' => $application->email, 'phone' => $application->phone, 'membership_category_id' => $application->membership_category_id, 'membership_level_id' => $data['membership_level_id'], 'active' => false]);
            $transaction = $processor->create(['member_id' => $member->id, 'membership_level_id' => $data['membership_level_id'], 'type' => 'Application', 'latitude' => $application->latitude, 'longitude' => $application->longitude, 'notes' => 'Submitted application: '.$application->reference_no], $request->user()->id);
            $application->update(['status' => 'registered', 'member_id' => $member->id, 'membership_transaction_id' => $transaction->id, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_notes' => $data['review_notes'] ?? null]);
        });

        return redirect()->route('applications.show', $application)->with('success', $data['action'] === 'register' ? 'Member registered with a pending transaction. Record payment and approve membership separately.' : 'Application rejected.');
    }

    public function resend(Request $request, MembershipApplication $application): RedirectResponse
    {
        if ($application->status !== 'pending' || $application->email_verified_at) {
            return back()->withErrors(['email' => 'Only pending, unconfirmed applications need a confirmation email.']);
        }
        if ($application->verification_queued_at?->gt(now()->subMinute())) {
            return back()->withErrors(['email' => 'Wait a minute before requesting another confirmation email.']);
        }
        $application->update(['verification_queued_at' => now()]);
        Notification::route('mail', $application->email)->notify(new MembershipApplicationSubmitted($application));

        return back()->with('success', 'Confirmation email queued again.');
    }
}
