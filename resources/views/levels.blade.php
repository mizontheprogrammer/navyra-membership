@extends('workspace-layout')
@section('title','Membership Levels')
@section('content')
<div class="console-heading"><div><h1>Membership Levels</h1><p>Configure fees in PHP, validity in days, and benefits. Blank fee means not configured; 0 means free.</p></div><a class="console-primary" href="{{ route('levels.create') }}">+ Add Level</a></div>
<section class="member-panel"><div class="member-table-wrap"><table class="member-table"><thead><tr><th>Name</th><th>Fee / Validity</th><th>Benefits</th><th>Actions</th></tr></thead><tbody>
@forelse($records as $record)<tr><td><strong>{{ $record->name }}</strong></td><td>{{ $record->fee!==null?'PHP '.number_format((float)$record->fee,2):'Fee not set' }}<small>{{ $record->validity_days?$record->validity_days.' days':'No expiry' }}</small></td><td><span class="catalog-benefits">{{ Str::limit($record->benefits?:'Not configured',100) }}</span></td><td><a href="{{ route('levels.edit',$record) }}">Edit</a><details class="member-delete"><summary>Delete</summary><p>Delete this level? Assigned records are protected.</p><form method="POST" action="{{ route('levels.destroy',$record) }}">@csrf @method('DELETE')<button class="member-danger">Confirm Delete</button></form></details></td></tr>@empty<tr><td colspan="4">No levels yet.</td></tr>@endforelse</tbody></table></div></section>
@endsection
