{{-- Timeline of the individual edits folded into one grouped change-log row. --}}
<div class="space-y-4 text-sm">
    @forelse ($steps as $step)
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <div class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                {{ $step['at'] }}
            </div>
            <ul class="list-disc space-y-0.5 ps-5 text-gray-700 dark:text-gray-300">
                @foreach ($step['changes'] as $change)
                    <li>{{ $change }}</li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">No change details recorded.</p>
    @endforelse
</div>
