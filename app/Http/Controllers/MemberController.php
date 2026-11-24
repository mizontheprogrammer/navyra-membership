<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'integer'], 'level' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $members = Member::with(['membershipCategory', 'membershipLevel'])
            ->when($data['search'] ?? null, function ($query, $value) {
                $query->where(function ($q) use ($value) {
                    $q->whereLike('name', '%'.$value.'%')->orWhereLike('membership_number', '%'.$value.'%')->orWhereHas('membershipCategory', fn ($c) => $c->whereLike('name', '%'.$value.'%'))->orWhereHas('membershipLevel', fn ($l) => $l->whereLike('name', '%'.$value.'%'));
                });
            })
            ->when($data['category'] ?? null, fn ($q, $value) => $q->where('membership_category_id', $value))
            ->when($data['level'] ?? null, fn ($q, $value) => $q->where('membership_level_id', $value))
            ->when($data['status'] ?? null, fn ($q, $value) => $q->where('active', $value === 'active'))
            ->orderBy('name')->paginate(10)->withQueryString();

        return view('members', ['members' => $members, 'categories' => MembershipCategory::orderBy('name')->get(), 'levels' => MembershipLevel::orderBy('id')->get()]);
    }

    private function form(Member $member): View
    {
        return view('member-form', ['member' => $member, 'categories' => MembershipCategory::orderBy('name')->get(), 'levels' => MembershipLevel::orderBy('id')->get()]);
    }

    public function create(): View
    {
        return $this->form(new Member);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = Member::create(collect($this->details($request))->except('confirm_deactivation')->all());

        return redirect()->route('members.show', $member)->with('success', 'Member registered. Create an application to process membership.');
    }

    public function show(Member $member): View
    {
        return view('member-profile', ['member' => $member->load(['membershipCategory', 'membershipLevel']), 'recentTransactions' => $member->transactions()->latest()->limit(5)->get()]);
    }

    public function edit(Member $member): View
    {
        return $this->form($member);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $data = $this->details($request, $member);
        if ($member->transactions()->exists() && ((int) $data['membership_level_id'] !== $member->membership_level_id || ($data['membership_number'] ?? $member->membership_number) !== $member->membership_number)) {
            return back()->withErrors(['membership_level_id' => 'For a member with transactions, change the level through an application or renewal. The membership number is permanent.'])->withInput();
        }
        $member->update(collect($data)->except('confirm_deactivation')->all());

        return redirect()->route('members.show', $member)->with('success', 'Member details updated.');
    }

    public function confirmStatus(Request $request, Member $member): View
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        return view('member-status-confirm', ['member' => $member, 'active' => (bool) $data['active']]);
    }

    public function status(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean'], 'confirm_deactivation' => Rule::when(! $request->boolean('active'), ['required', 'accepted'], ['nullable'])]);
        unset($data['confirm_deactivation']);
        if ($data['active'] && $member->expires_on?->lt(today())) {
            return back()->withErrors(['active' => 'This membership has expired. Approve a paid renewal to reactivate it.']);
        }
        $member->update($data);

        return redirect()->route('members.show', $member)->with('success', 'Member status updated.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        if ($member->transactions()->exists() || MembershipApplication::where('member_id', $member->id)->exists()) {
            return back()->withErrors(['member' => 'Members with transaction history cannot be deleted. Deactivate them to preserve records.']);
        }
        $member->delete();

        return redirect()->route('members.index')->with('success', 'Member deleted.');
    }

    public function history(Member $member): View
    {
        return view('member-history', ['member' => $member, 'transactions' => $member->transactions()->with('payments')->latest()->paginate(10)]);
    }

    public function record(Member $member): View
    {
        return view('member-record', compact('member'));
    }

    private function details(Request $request, ?Member $member = null): array
    {
        return $request->validate([
            'membership_number' => [$member ? 'required' : 'nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('members')->ignore($member?->id)],
            'confirm_deactivation' => Rule::when($member?->active && ! $request->boolean('active'), ['required', 'accepted'], ['nullable']),
            'company_name' => [Rule::requiredIf(fn () => MembershipCategory::find($request->input('membership_category_id'))?->name === 'Corporate'), 'nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'], 'membership_category_id' => ['required', 'integer', 'exists:membership_categories,id'],
            'membership_level_id' => ['required', 'integer', 'exists:membership_levels,id'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'active' => ['required', 'boolean'],
        ]);
    }
}
