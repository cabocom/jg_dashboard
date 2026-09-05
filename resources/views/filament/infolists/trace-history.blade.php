@php
    $entryRecord = isset($getRecord) && is_callable($getRecord) ? $getRecord() : ($record ?? null);
    $traces = $entryRecord?->trace_history_json ?? [];
@endphp

<div class="space-y-4">
    @if(empty($traces))
        <div class="text-sm text-gray-500 dark:text-gray-400 italic p-4 bg-gray-900/50 rounded-xl border border-gray-800">
            Sin eventos registrados en la traza.
        </div>
    @else
        <div class="relative pl-6 border-l-2 border-emerald-500/30 dark:border-emerald-500/20 space-y-6 my-2">
            @foreach($traces as $index => $trace)
                @php
                    $event = $trace['event'] ?? 'EVENT';
                    $timestamp = $trace['timestamp'] ?? '-';
                    $detail = $trace['detail'] ?? '';
                    $thread = $trace['thread_id'] ?? null;
                    
                    $colorClasses = match($event) {
                        'HTTP_REQUEST_RECEIVED' => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                        'TASK_POPPED_FROM_QUEUE' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                        'TASK_COMPLETED' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                        'TASK_FAILED' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                        default => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
                    };
                @endphp
                <div class="relative group">
                    <!-- Dot marker -->
                    <div class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-4 ring-gray-950"></div>
                    
                    <div class="p-4 rounded-xl bg-gray-900/80 border border-gray-800 shadow-sm hover:border-gray-700 transition">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $colorClasses }}">
                                {{ $event }}
                            </span>
                            <span class="font-mono text-xs text-gray-400">
                                {{ $timestamp }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-200 font-sans leading-relaxed">
                            {{ $detail }}
                        </p>
                        @if($thread)
                            <div class="mt-2 text-xs font-mono text-gray-400">
                                Thread: <span class="text-gray-300">{{ $thread }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
