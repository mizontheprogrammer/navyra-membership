<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{ $setup?'Administrator setup':'Login' }} | Navyra Membership</title>@vite(['resources/css/tasks.css'])</head>
<body class="auth-page"><main class="auth-card"><a class="navy-brand" href="{{ route('login') }}">@include('brand')</a><h1>{{ $setup?'Create your administrator account':'Welcome back' }}</h1><p>{{ $setup?'One-time setup. Choose the credentials you will use to manage the system.':'Log in to manage members and transactions.' }}</p>
@if($errors->any())<div class="system-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ $setup?route('setup.store'):route('login.store') }}">@csrf
@if($setup)<label for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">@endif
<label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
<label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="{{ $setup?'new-password':'current-password' }}" @if($setup) minlength="10" @endif>
@if($setup)<p class="member-input-hint">At least 10 characters with uppercase, lowercase, and a number.</p><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">@endif
<button type="submit">{{ $setup?'Create Administrator':'Log in' }}</button></form><p><a href="{{ route('membership.create') }}">Apply for membership →</a></p></main></body></html>
