@extends('workspace-layout')
@section('title','Transactions')
@section('content')
<div class="console-heading"><div><h1>Membership transactions</h1><p>Applications, renewals, and approval status.</p></div><a class="console-primary" href="{{ route('transactions.create') }}">+ New Transaction</a></div>
<section class="member-panel"><form class="member-search" method="GET"><div><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Reference, member name, or number"></div><div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(\App\Models\MembershipTransaction::STATUSES as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div><button>Search</button><a href="{{ route('transactions.index') }}">Reset</a></form>@include('transaction-table')@include('simple-pagination',['records'=>$transactions])</section>
@endsection
