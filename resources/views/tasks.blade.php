@extends('workspace-layout')
@section('title', 'Tasks')
@section('content')
<div class="navy-tasks-content">
    <div class="console-heading"><div><p class="console-eyebrow">YOUR PERSONAL TO-DO LIST</p><h1>My Tasks</h1><p>One place for your next steps.</p></div></div>



    <form action="{{ route('tasks.store') }}" method="POST">
        @csrf
        <label for="task_title">Task title:</label><br>
        <input type="text" id="task_title" name="task_title"
               value="{{ old('task_title') }}"><br>
        @error('task_title')
            <p>{{ $message }}</p>
        @enderror

        <label for="description">Description:</label><br>
        <textarea id="description" name="description">{{ old('description') }}</textarea><br>
        <button type="submit">Add Task</button>
    </form>

    <h2>Saved Tasks</h2>
    @forelse ($tasks as $task)
        <article>
            <h3>{{ $task->task_title }}</h3>
            <p>{{ $task->description ?: 'No description provided.' }}</p>
            <p>Status: {{ $task->completed ? 'Completed' : 'Not completed' }}</p>
        </article>
        <hr>
    @empty
        <p>No tasks have been added yet.</p>
    @endforelse
</div>
@endsection
