@extends('workspace-layout')
@section('title','Member Transaction History')
@section('content')
<a class="member-back" href="{{ route('members.show',$member) }}">← Member profile</a><div class="console-heading"><div><h1>{{ $member->name }}</h1><p>{{ $member->membership_number }} · Transaction history</p></div><a class="console-primary" href="{{ route('transactions.create',['member'=>$member->id,'type'=>'Renewal']) }}">New Renewal</a></div><section class="member-panel">@include('transaction-table')@include('simple-pagination',['records'=>$transactions])</section>
@endsection
