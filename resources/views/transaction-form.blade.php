@extends('workspace-layout')
@section('title','New Transaction')
@section('content')
<a class="member-back" href="{{ route('transactions.index') }}">← Transactions</a><h1>Create membership transaction</h1>
<form class="member-panel member-editor catalog-editor" data-map-provider="{{ config('services.maps.provider') }}" data-weather-url="{{ route('weather.show') }}" data-maps-key="{{ config('services.google_maps.key') }}" data-map-id="{{ config('services.google_maps.map_id') }}" method="POST" action="{{ route('transactions.store') }}">@csrf
<label for="member_id">Member *</label><select id="member_id" name="member_id" required><option value="">Choose a member</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected((string)old('member_id',$selectedMember)===(string)$member->id)>{{ $member->name }} · {{ $member->membership_number }}</option>@endforeach</select>
<p class="member-input-hint">Missing member? <a href="{{ route('members.create') }}">Register a member first.</a></p>
<label for="type">Transaction type *</label><select id="type" name="type">@foreach(['Application','Renewal'] as $type)<option @selected(old('type',$selectedType)===$type)>{{ $type }}</option>@endforeach</select>
<label for="membership_level_id">Membership level *</label><select id="membership_level_id" name="membership_level_id" required><option value="">Choose a level</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected((string)old('membership_level_id')===(string)$level->id) @disabled($level->fee===null)>{{ $level->name }} · {{ $level->fee===null?'Fee not configured':'PHP '.number_format((float)$level->fee,2) }} · {{ $level->validity_days?$level->validity_days.' days':'No expiry' }}</option>@endforeach</select>
<p class="member-input-hint">The current level fee and validity are saved with this transaction. <a href="{{ route('levels.index') }}">Configure levels</a></p>
@include('location-widget')
<label for="notes">Notes (optional)</label><textarea id="notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea><div class="member-form-actions"><button>Create Transaction</button></div></form>
@endsection
