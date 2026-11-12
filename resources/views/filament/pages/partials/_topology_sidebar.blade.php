<!-- Slide-over / Panel Lateral de Detalles del Nodo Seleccionado -->
@if ($selectedNode)
    <div class="absolute top-0 right-0 w-96 h-full bg-gray-900/95 backdrop-blur-lg border-l border-gray-800 p-6 overflow-y-auto shadow-2xl z-20 transition-all duration-300">
        <div class="flex items-start justify-between pb-4 border-b border-gray-800">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center 
                    {{ in_array($selectedNode['status'] ?? '', ['healthy', 'running']) ? 'bg-emerald-950/80 text-emerald-400 border border-emerald-800' : '' }}
                    {{ in_array($selectedNode['status'] ?? '', ['down', 'exited', 'dead']) ? 'bg-rose-950/80 text-rose-400 border border-rose-800' : '' }}
                    {{ in_array($selectedNode['status'] ?? '', ['warning', 'restarting', 'paused']) ? 'bg-amber-950/80 text-amber-400 border border-amber-800' : '' }}
                    {{ ($selectedNode['type'] ?? '') === 'host' ? 'bg-blue-950/80 text-blue-400 border border-blue-800' : '' }}
                ">
                    @if(($selectedNode['type'] ?? '') === 'host')
                        <x-heroicon-o-server class="w-6 h-6" />
                    @elseif(($selectedNode['type'] ?? '') === 'database')
                        <x-heroicon-o-circle-stack class="w-6 h-6" />
                    @else
                        <x-heroicon-o-cube class="w-6 h-6" />
                    @endif
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-100 leading-tight">{{ $selectedNode['label'] ?? $selectedNode['id'] }}</h3>
                    <span class="text-xs text-gray-400 uppercase tracking-wider font-mono">{{ $selectedNode['type'] ?? 'Servicio' }}</span>
                </div>
            </div>
            <button type="button" wire:click="closeNodeDetails" class="text-gray-400 hover:text-white p-1 rounded-md hover:bg-gray-800 transition-colors">
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>

        <!-- Estado y badges -->
        <div class="mt-4 space-y-4">
            <div>
                <span class="text-xs font-medium text-gray-400 uppercase">Estado Operacional</span>
                <div class="mt-1">
                    @php
                        $st = $selectedNode['status'] ?? 'unknown';
                    @endphp
                    @if(in_array($st, ['healthy', 'running']))
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-950 text-emerald-300 border border-emerald-800">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Saludable / Running
                        </span>
                    @elseif(in_array($st, ['down', 'exited', 'dead']))
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-rose-950 text-rose-300 border border-rose-800">
                            <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                            Caído / Exited
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-950 text-amber-300 border border-amber-800">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            {{ ucfirst($st) }}
                        </span>
                    @endif
                </div>
            </div>

            <!-- Metadatos técnicos -->
            @if (!empty($selectedNode['metadata']))
                <div class="bg-gray-950/70 rounded-lg p-3 border border-gray-800 space-y-2 text-xs">
                    <div class="font-semibold text-gray-300 mb-1">Metadatos del Contenedor</div>
                    @foreach ($selectedNode['metadata'] as $key => $val)
                        <div class="flex justify-between items-center py-0.5 border-b border-gray-900 last:border-0">
                            <span class="text-gray-400 capitalize">{{ str_replace('_', ' ', $key) }}:</span>
                            <span class="text-gray-200 font-mono text-right truncate max-w-[180px]">{{ is_array($val) ? json_encode($val) : $val }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Conexiones / Relaciones del nodo -->
            <div>
                <span class="text-xs font-medium text-gray-400 uppercase">Relaciones de Red</span>
                <div class="mt-2 space-y-1.5">
                    @php
                        $nodeId = $selectedNode['id'];
                        $connected = collect($edges)->filter(fn($e) => ($e['from'] ?? '') === $nodeId || ($e['to'] ?? '') === $nodeId);
                    @endphp

                    @forelse ($connected as $rel)
                        @php
                            $isOutgoing = ($rel['from'] ?? '') === $nodeId;
                            $targetId = $isOutgoing ? ($rel['to'] ?? '') : ($rel['from'] ?? '');
                        @endphp
                        <div wire:click="selectNode('{{ $targetId }}')" class="flex items-center justify-between p-2 rounded bg-gray-800/60 hover:bg-gray-800 border border-gray-700/50 cursor-pointer transition-colors text-xs">
                            <div class="flex items-center gap-2">
                                @if($isOutgoing)
                                    <x-heroicon-m-arrow-right class="w-3.5 h-3.5 text-emerald-400" />
                                @else
                                    <x-heroicon-m-arrow-left class="w-3.5 h-3.5 text-sky-400" />
                                @endif
                                <span class="font-medium text-gray-200">{{ $targetId }}</span>
                            </div>
                            <span class="text-gray-400 text-[10px] uppercase font-mono">{{ str_replace('_', ' ', $rel['relation'] ?? 'rel') }}</span>
                        </div>
                    @empty
                        <div class="text-xs text-gray-500 italic">Sin conexiones directas detectadas.</div>
                    @endforelse
                </div>
            </div>

            <!-- Botón de acción rápida -->
            <div class="pt-4 border-t border-gray-800 flex flex-col gap-2">
                <a href="{{ url('/admin/agent-tasks') }}" class="w-full text-center px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs rounded-lg transition-colors shadow-sm">
                    Ver Tareas del Agente
                </a>
            </div>
        </div>
    </div>
@endif

