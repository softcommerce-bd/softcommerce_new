@forelse ($jobs as $job)
    <div class="card mb-3 border cursor-pointer px-3 py-3">
        <a href="{{ route('career.page.detail', $job->id) }}" class="text-decoration-none text-dark">
            <h5 class="text-primary mb-2">{{ $job->job_title }}</h5>
            <p class="text-muted mt-2 mb-0">{!! $job->short_description !!}</p>
        </a>
    </div>
@empty
    <p class="text-muted">No jobs found for selected departments.</p>
@endforelse