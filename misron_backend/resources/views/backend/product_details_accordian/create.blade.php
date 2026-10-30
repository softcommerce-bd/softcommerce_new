@extends('backend.layouts.app')

@section('content')
<div class="container mt-5">
    <h2>Add New Accordion</h2>

    <form action="{{ route('accordions.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required>
            @error('title')
            <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="content">Content:</label>
            <textarea name="content" id="content" class="form-control aiz-text-editor" rows="5" required>{{ old('content') }}</textarea>
            @error('content')
            <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mt-3">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('accordions.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
