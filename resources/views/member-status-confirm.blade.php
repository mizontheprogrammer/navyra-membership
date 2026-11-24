@extends('workspace-layout')
@section('title',$active?'Reactivate Member':'Deactivate Member')
@section('content')
<a class="member-back" href="{{ route('members.show',$member) }}">← Member profile</a>
<section class="member-panel member-editor"><h1>{{ $active?'Reactivate':'Deactivate' }} member?</h1><p><strong>{{ $member->name }}</strong> · {{ $member->membership_number }}</p><p>{{ $active?'This will mark the member as active. An expired membership requires an approved renewal.':'This will mark the member as inactive. Their profile, membership number, payments, and transaction history will remain saved.' }}</p>
<form method="POST" action="{{ route('members.status',$member) }}">@csrf @method('PATCH')<input type="hidden" name="active" value="{{ (int)$active }}">@if(!$active)<input type="hidden" name="confirm_deactivation" value="1">@endif<div class="member-form-actions"><button class="{{ $active?'member-secondary':'member-danger' }}">Confirm {{ $active?'Reactivation':'Deactivation' }}</button><a href="{{ route('members.show',$member) }}">Cancel</a></div></form></section>
@endsection
