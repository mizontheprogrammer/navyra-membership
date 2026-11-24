<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Services\MembershipProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(MembershipTransaction::STATUSES)]]);
        $transactions = MembershipTransaction::with('member')->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('reference_number', '%'.$s.'%')->orWhereLike('member_name', '%'.$s.'%')->orWhereLike('membership_number', '%'.$s.'%')))->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->latest()->paginate(15)->withQueryString();

        return view('transactions', compact('transactions'));
    }

    public function create(Request $request): View
    {
        return view('transaction-form', ['members' => Member::orderBy('name')->get(), 'levels' => MembershipLevel::orderBy('id')->get(), 'selectedMember' => $request->query('member'), 'selectedType' => $request->query('type', 'Application')]);
    }

    public function store(Request $request, MembershipProcessor $processor): RedirectResponse
    {
        $data = $request->validate(['member_id' => ['required', 'integer', 'exists:members,id'], 'membership_level_id' => ['required', 'integer', 'exists:membership_levels,id'], 'type' => ['required', Rule::in(['Application', 'Renewal'])], 'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $transaction = $processor->create($data, $request->user()->id);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction created with a unique reference number.');
    }

    public function show(MembershipTransaction $transaction): View
    {
        return view('transaction-detail', ['transaction' => $transaction->load(['member', 'payments.recorder', 'creator', 'approver', 'corrections.performer'])]);
    }

    public function status(Request $request, MembershipTransaction $transaction, MembershipProcessor $processor): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'cancel', 'mark-free-paid', 'reverse'])], 'reason' => ['required_if:action,reverse', 'nullable', 'string', 'min:5', 'max:2000']]);
        $processor->changeStatus($transaction, $data['action'], $request->user()->id, $data['reason'] ?? null);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction status updated.');
    }
}
