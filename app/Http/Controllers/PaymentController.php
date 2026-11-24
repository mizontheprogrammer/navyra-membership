<?php

namespace App\Http\Controllers;

use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Services\MembershipProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $payments = Payment::with(['transaction', 'recorder'])->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('receipt_number', '%'.$s.'%')->orWhereHas('transaction', fn ($t) => $t->whereLike('member_name', '%'.$s.'%')->orWhereLike('reference_number', '%'.$s.'%'))))->latest()->paginate(15)->withQueryString();

        return view('payments', compact('payments'));
    }

    public function create(Request $request): View
    {
        return view('payment-form', ['transactions' => MembershipTransaction::where('status', 'Pending')->where('amount', '>', 0)->with('member')->latest()->get(), 'selectedTransaction' => $request->query('transaction'), 'submissionKey' => (string) Str::uuid()]);
    }

    public function store(Request $request, MembershipProcessor $processor): RedirectResponse
    {
        $data = $request->validate([
            'membership_transaction_id' => ['required', 'integer', 'exists:membership_transactions,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'], 'method' => ['required', Rule::in(Payment::METHODS)],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'external_reference' => ['nullable', 'required_unless:method,Cash', 'string', 'max:255'],
            'submission_key' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $transaction = MembershipTransaction::findOrFail($data['membership_transaction_id']);
        if ($data['paid_on'] < $transaction->created_at->toDateString()) {
            return back()->withErrors(['paid_on' => 'Payment date cannot precede the transaction date.'])->withInput();
        }
        $payment = $processor->pay($transaction, $data, $request->user()->id);

        return redirect()->route('payments.receipt', $payment)->with('success', 'Payment recorded.');
    }

    public function void(Request $request, Payment $payment, MembershipProcessor $processor): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $processor->voidPayment($payment, $data['reason'], $request->user()->id);

        return redirect()->route('transactions.show', $payment->membership_transaction_id)->with('success', 'Payment voided. The original receipt and correction history are preserved.');
    }

    public function receipt(Payment $payment): View
    {
        return view('receipt', ['payment' => $payment->load(['transaction.member', 'recorder'])]);
    }
}
