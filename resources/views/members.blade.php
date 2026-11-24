@extends('workspace-layout')
@section('title','Members')
@section('content')
<div class="console-heading"><div><h1>Member Management</h1><p>Register members and manage their profiles.</p></div><a class="console-primary" href="{{ route('members.create') }}">+ Add Member</a></div>
<section class="member-panel"><form class="member-search" action="{{ route('members.index') }}" method="GET">
<div><label for="search">Search members</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Name, number, category, or level"></div>
<div><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
<div><label for="level">Level</label><select id="level" name="level"><option value="">All levels</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected((string)request('level')===(string)$level->id)>{{ $level->name }}</option>@endforeach</select></div>
<div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></div>
<button>Search</button><a href="{{ route('members.index') }}">Reset</a></form>
<div class="member-table-wrap"><table class="member-table"><thead><tr><th>Member</th><th>Membership no.</th><th>Category</th><th>Level</th><th>Status</th><th>Actions</th></tr></thead><tbody>@forelse($members as $member)<tr><td><a class="member-name" href="{{ route('members.show',$member) }}">{{ $member->name }}</a><small>{{ $member->email }}</small></td><td>{{ $member->membership_number }}</td><td>{{ $member->category }}</td><td>{{ $member->level }}</td><td><span class="member-status {{ $member->active?'':'inactive' }}">{{ $member->active?'Active':'Inactive' }}</span></td><td class="member-row-actions"><a href="{{ route('members.show',$member) }}">View</a><a href="{{ route('members.edit',$member) }}">Edit</a></td></tr>@empty<tr><td colspan="6"><div class="member-empty"><h2>No matching members</h2><p>Add a member or change your filters.</p></div></td></tr>@endforelse</tbody></table></div>
@include('simple-pagination',['records'=>$members])</section>
@endsection
