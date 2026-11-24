@extends('workspace-layout')
@section('title','Membership Categories')
@section('content')
<div class="console-heading"><div><h1>Membership Categories</h1><p>Manage member groups.</p></div><a class="console-primary" href="{{ route('categories.create') }}">+ Add Category</a></div>
<section class="member-panel"><div class="member-table-wrap"><table class="member-table"><thead><tr><th>Name</th><th>Description / Client application</th><th>Members</th><th>Actions</th></tr></thead><tbody>
@forelse($records as $record)<tr><td><strong>{{ $record->name }}</strong></td><td>{{ $record->description?:'—' }}<small>{{ $record->level?:'No public label' }} · PHP {{ number_format((float)$record->fee,2) }} · {{ $record->is_active?'Accepting applications':'Inactive for applications' }}</small></td><td>{{ $record->members_count }}</td><td><a href="{{ route('categories.edit',$record) }}">Edit</a><details class="member-delete"><summary>Delete</summary><p>Delete this category? Assigned records are protected.</p><form method="POST" action="{{ route('categories.destroy',$record) }}">@csrf @method('DELETE')<button class="member-danger">Confirm Delete</button></form></details></td></tr>@empty<tr><td colspan="4">No categories yet.</td></tr>@endforelse</tbody></table></div></section>
@endsection
