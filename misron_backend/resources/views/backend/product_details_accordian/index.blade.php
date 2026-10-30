@extends('backend.layouts.app')

@section('content')
<div class="container mt-5">
    <h2>Accordion Items</h2>
    <div class="d-flex justify-content-between mb-3">
        <a href="{{ route('accordions.create') }}" class="btn btn-primary">Add New Accordion</a>
    </div>

    @if($accordions->isEmpty())
        <div class="alert alert-warning">No accordion items found. Add one now!</div>
    @else
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Content</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accordions as $key => $accordion)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $accordion->title }}</td>
                    <td>
                        <button class="btn btn-link text-primary" type="button" data-toggle="collapse" data-target="#content-{{ $key }}" aria-expanded="false" aria-controls="content-{{ $key }}">
                            View Content
                        </button>
                        <div id="content-{{ $key }}" class="collapse">
                            <p class="mt-2">{{ $accordion->content }}</p>
                        </div>
                    </td>
                    <td>
                        <a href="{{ route('accordions.edit', $accordion->id) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form action="{{ route('accordions.destroy', $accordion->id) }}" method="POST" class="d-inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this item?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
