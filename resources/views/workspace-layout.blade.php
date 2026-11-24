<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>@yield('title','Dashboard') | Navyra Membership</title>@include('theme-init')@vite(['resources/css/tasks.css','resources/js/workspace.js','resources/js/membership-application.js'])</head>
<body class="console-page navy-workspace left-workspace">
<aside class="left-sidebar"><a class="navy-brand" href="{{ route('dashboard') }}">@include('brand')</a>
<nav aria-label="Main navigation">
@foreach(['dashboard'=>['Dashboard','dashboard'],'members.index'=>['Members','members.*'],'applications.index'=>['Applications','applications.*'],'categories.index'=>['Categories','categories.*'],'levels.index'=>['Levels','levels.*'],'transactions.index'=>['Transactions','transactions.*'],'payments.index'=>['Payments','payments.*'],'tasks.index'=>['Tasks','tasks.*']] as $routeName => [$label,$pattern])
<a class="left-nav {{ request()->routeIs($pattern)?'selected':'' }}" href="{{ route($routeName) }}" @if(request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav><div class="admin-account"><span>{{ auth()->user()?->name }}</span><small>Administrator</small><a class="admin-client-link" href="{{ route('membership.create') }}" target="_blank" rel="noopener">Open Client Form ↗</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form></div></aside>
<main class="console-main"><header class="left-page-header"><span>@yield('title','Dashboard')</span><button type="button" id="theme-toggle" aria-pressed="false">Dark mode</button></header><div class="console-content">
@if(session('success'))<div class="member-notice" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="system-errors" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</div></main></body></html>
