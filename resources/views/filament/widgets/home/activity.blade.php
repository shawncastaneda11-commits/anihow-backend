{{-- The latest activity this person may read. --}}
<x-filament::section heading="Recent activity">
    @if ($activity->isEmpty())
        <p class="anihow-muted">Nothing recorded yet.</p>
    @else
        <ul class="anihow-home-activity">
            @foreach ($activity as $row)
                <li>
                    <span class="anihow-home-strong">{{ $row->user?->is(auth()->user()) ? 'You' : ($row->user?->name ?? 'Deleted account') }}</span>
                    <span>{{ \Illuminate\Support\Str::lower(\App\Models\ActivityLog::ACTIONS[$row->action] ?? $row->action) }} {{ $row->subjectName() }}{{ $row->subject_label ? ': '.$row->subject_label : '' }}</span>
                    <span class="anihow-muted">{{ $row->created_at?->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-filament::section>
