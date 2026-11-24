@extends('workspace-layout')
@section('title','Record Payment')
@section('content')
<a class="member-back" href="{{ route('payments.index') }}">← Payments</a><h1>Record payment</h1>
<form class="member-panel member-editor catalog-editor" action="{{ route('payments.store') }}" method="POST">@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key',$submissionKey) }}">
<label for="membership_transaction_id">Pending transaction *</label><select id="membership_transaction_id" name="membership_transaction_id" required><option value="">Choose a transaction</option>@foreach($transactions as $transaction)<option value="{{ $transaction->id }}" @selected((string)old('membership_transaction_id',$selectedTransaction)===(string)$transaction->id)>{{ $transaction->member_name }} · {{ $transaction->reference_number }} · Balance PHP {{ \App\Support\Money::decimal($transaction->balanceCents()) }}</option>@endforeach</select>
@if(!$transactions->count())<p class="member-input-hint">No transactions awaiting payment. <a href="{{ route('transactions.create') }}">Create an application or renewal.</a></p>@endif
<label for="amount">Amount (PHP) *</label><input id="amount" type="number" name="amount" min="0.01" max="99999999.99" step="0.01" value="{{ old('amount') }}" required><p class="member-input-hint">Partial payments are allowed. Overpayments are rejected.</p>
<label for="method">Payment method *</label><select id="method" name="method">@foreach(\App\Models\Payment::METHODS as $method)<option @selected(old('method')===$method)>{{ $method }}</option>@endforeach</select>
<label for="external_reference">Payment reference (required for non-cash)</label><input id="external_reference" name="external_reference" value="{{ old('external_reference') }}" maxlength="255">
<label for="paid_on">Payment date *</label><input id="paid_on" name="paid_on" type="date" value="{{ old('paid_on',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
<label for="notes">Notes (optional)</label><textarea id="notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea><div class="member-form-actions"><button @disabled(!$transactions->count())>Record Payment</button></div><p class="member-input-hint">This records a payment already received; it does not charge a bank account or card.</p></form>
@endsection
