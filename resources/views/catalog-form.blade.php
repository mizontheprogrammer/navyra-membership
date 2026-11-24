@extends('workspace-layout')
@section('title',($record->exists?'Edit ':'Add ').$label)
@section('content')
<a class="member-back" href="{{ route($kind.'.index') }}">← All {{ $kind }}</a><h1>{{ $record->exists?'Edit':'Add' }} {{ $label }}</h1>
<form class="member-panel member-editor catalog-editor" method="POST" action="{{ $record->exists?route($kind.'.update',$record):route($kind.'.store') }}">@csrf @if($record->exists) @method('PUT') @endif
<label for="name">Name *</label><input id="name" name="name" value="{{ old('name',$record->name) }}" required maxlength="100">
@if($kind==='categories')<label for="description">Description</label><textarea id="description" name="description" maxlength="5000">{{ old('description',$record->description) }}</textarea>
<label for="level">Public category label (optional)</label><input id="level" name="level" maxlength="100" value="{{ old('level',$record->level) }}" placeholder="e.g. Standard"><p class="member-input-hint">Shown on the client application. This label does not replace the membership tier.</p>
<label for="fee">Category fee shown on application (PHP)</label><input id="fee" name="fee" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('fee',$record->fee??'0.00') }}" required><p class="member-input-hint">Reference amount only. The existing membership transaction uses the administrator-selected tier’s fee.</p>
<label for="is_active">Public application availability</label><select id="is_active" name="is_active"><option value="1" @selected((string)old('is_active',$record->exists?(int)$record->is_active:1)==='1')>Active — accept applications</option><option value="0" @selected((string)old('is_active',$record->exists?(int)$record->is_active:1)==='0')>Inactive — hide from client form</option></select>
@else
<label for="fee">Fee (PHP)</label><input id="fee" name="fee" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('fee',$record->fee) }}"><p class="member-input-hint">Leave blank if not configured. Enter 0 for a free membership.</p>
<label for="validity_days">Validity period (days)</label><input id="validity_days" name="validity_days" type="number" min="1" max="36500" value="{{ old('validity_days',$record->validity_days) }}"><p class="member-input-hint">Leave blank for no expiry. The period begins when the transaction is approved; early renewals extend the existing end date.</p>
<label for="benefits">Benefits</label><textarea id="benefits" name="benefits" maxlength="5000">{{ old('benefits',$record->benefits) }}</textarea><p class="member-input-hint">Existing transactions keep their original fee, validity, and benefit details.</p>
@endif
<div class="member-form-actions"><button>Save {{ $label }}</button><a href="{{ route($kind.'.index') }}">Cancel</a></div></form>
@endsection
