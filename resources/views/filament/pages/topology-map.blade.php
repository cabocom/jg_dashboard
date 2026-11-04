<x-filament-panels::page>
    <div wire:poll.5s="refreshTopology" class="space-y-6" x-data="topologyNetwork({
        initialNodes: @js($nodes),
        initialEdges: @js($edges)
    })">
        <!-- 1. Contenedor Principal del Grafo Interactivo 2D (Arriba) -->
        <div class="relative bg-gray-950 border border-gray-800 rounded-xl overflow-hidden shadow-2xl">
            <!-- Barra superior limpia y espaciada -->
            <div class="flex items-center justify-between bg-gray-900/90 border-b border-gray-800 px-4 py-3">
                <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-950 text-emerald-400 border border-emerald-800/60">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Auto-Sync (5s)
                    </span>
                    <span class="text-gray-400 text-xs">Última actualización: <strong class="text-gray-200">{{ $lastUpdated }}</strong></span>
                </div>

                <div class="flex items-center space-x-2">
                    <button type="button" @click="fitNetwork()" title="Centrar y ajustar mapa" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-300 hover:text-white bg-gray-800/80 hover:bg-gray-700 border border-gray-700 rounded-lg transition-colors shadow-sm">
                        <x-heroicon-o-viewfinder-circle class="w-4 h-4 text-sky-400" />
                        Centrar
                    </button>
                    <button type="button" @click="togglePhysics()" :title="physicsEnabled ? 'Pausar simulación física' : 'Activar simulación física'" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-gray-800/80 hover:bg-gray-700 border border-gray-700 rounded-lg transition-colors shadow-sm" :class="physicsEnabled ? 'text-emerald-400 border-emerald-700/50' : 'text-gray-400'">
                        <x-heroicon-o-bolt class="w-4 h-4" />
                        <span x-text="physicsEnabled ? 'Física ON' : 'Física OFF'"></span>
                    </button>
                    <button type="button" wire:click="refreshTopology(true)" wire:loading.attr="disabled" title="Sincronizar datos ahora" class="p-1.5 text-gray-300 hover:text-white bg-gray-800/80 hover:bg-gray-700 border border-gray-700 rounded-lg transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin text-emerald-400" />
                    </button>
                </div>
            </div>

            <!-- Leyenda de colores -->
            <div class="absolute bottom-4 left-4 z-10 flex items-center space-x-4 bg-gray-900/90 backdrop-blur-md border border-gray-700/60 rounded-lg px-3 py-2 text-xs shadow-lg">
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-emerald-400/20"></span>
                    <span class="text-gray-300 font-medium">Saludable</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 ring-2 ring-rose-400/20"></span>
                    <span class="text-gray-300 font-medium">Caído</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 ring-2 ring-amber-400/20"></span>
                    <span class="text-gray-300 font-medium">Advertencia</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500 ring-2 ring-blue-400/20"></span>
                    <span class="text-gray-300 font-medium">Host del Sistema</span>
                </div>
            </div>

            <!-- Canvas de Vis.js con espacio amplio de 720px -->
            <div wire:ignore x-ref="networkContainer" style="height: 720px; width: 100%; min-height: 720px; background-color: #080d1a;" class="w-full h-[720px] min-h-[720px] cursor-grab active:cursor-grabbing"></div>

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
        </div>

        <!-- 2. Tarjetas de métricas compactas en una sola fila debajo del mapa -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; width: 100%;">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5 flex items-center justify-between shadow-sm">
                <div>
                    <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Nodos</span>
                    <div class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-0.5">{{ $totalNodes }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300">
                    <x-heroicon-o-server-stack class="w-4 h-4" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-900/40 rounded-xl p-3.5 flex items-center justify-between shadow-sm">
                <div>
                    <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Saludables</span>
                    <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $healthyNodes }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-rose-200 dark:border-rose-900/40 rounded-xl p-3.5 flex items-center justify-between shadow-sm">
                <div>
                    <span class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider">Caídos</span>
                    <div class="text-xl font-bold text-rose-600 dark:text-rose-400 mt-0.5">{{ $downNodes }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800/50 flex items-center justify-center text-rose-600 dark:text-rose-400">
                    <x-heroicon-o-x-circle class="w-4 h-4" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-900/40 rounded-xl p-3.5 flex items-center justify-between shadow-sm">
                <div>
                    <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Advertencia</span>
                    <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-0.5">{{ $warningNodes }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5 flex items-center justify-between shadow-sm">
                <div>
                    <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Conexiones</span>
                    <div class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-0.5">{{ $totalEdges }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/50 flex items-center justify-center text-sky-600 dark:text-sky-400">
                    <x-heroicon-o-arrows-pointing-out class="w-4 h-4" />
                </div>
            </div>
        </div>
    </div>

    <!-- Script de Vis.js Network con física espaciada y conexiones amplias -->
    @push('scripts')
    <script src="https://unpkg.com/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('topologyNetwork', (config) => ({
                network: null,
                nodesDataSet: null,
                edgesDataSet: null,
                physicsEnabled: true,

                init() {
                    const setup = () => {
                        if (typeof vis !== 'undefined') {
                            this.initVisNetwork(config.initialNodes, config.initialEdges);
                        } else {
                            setTimeout(setup, 80);
                        }
                    };

                    this.$nextTick(setup);

                    // Escuchar eventos de actualización en tiempo real desde Livewire
                    this.$wire.$on('topology-updated', (data) => {
                        this.updateTopologyData(data[0].nodes, data[0].edges);
                    });
                },

                formatNode(n) {
                    const status = n.status || 'healthy';
                    const type = n.type || 'container';
                    let bgColor = '#064e3b'; // emerald-900
                    let borderColor = '#10b981'; // emerald-500
                    let fontColor = '#ecfdf5';
                    let shape = 'box';

                    if (type === 'host') {
                        bgColor = '#1e3a8a';
                        borderColor = '#3b82f6';
                        fontColor = '#eff6ff';
                        shape = 'box';
                    } else if (type === 'database') {
                        shape = 'database';
                    }

                    if (['down', 'exited', 'dead'].includes(status)) {
                        bgColor = '#7f1d1d';
                        borderColor = '#ef4444';
                        fontColor = '#fef2f2';
                    } else if (['warning', 'restarting', 'paused'].includes(status)) {
                        bgColor = '#78350f';
                        borderColor = '#f59e0b';
                        fontColor = '#fffbeb';
                    }

                    return {
                        id: n.id,
                        label: `\n${n.label || n.id}\n[${status.toUpperCase()}]`,
                        shape: shape,
                        margin: 16,
                        widthConstraint: { minimum: 160, maximum: 260 },
                        color: {
                            background: bgColor,
                            border: borderColor,
                            highlight: {
                                background: borderColor,
                                border: '#ffffff'
                            },
                            hover: {
                                background: bgColor,
                                border: '#ffffff'
                            }
                        },
                        font: {
                            color: fontColor,
                            size: 13,
                            face: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
                            bold: { color: fontColor }
                        },
                        borderWidth: 2,
                        shadow: {
                            enabled: true,
                            color: 'rgba(0,0,0,0.6)',
                            size: 10,
                            x: 0,
                            y: 5
                        }
                    };
                },

                formatEdge(e, idx) {
                    return {
                        id: `${e.from}->${e.to}-${idx}`,
                        from: e.from,
                        to: e.to,
                        label: e.relation ? e.relation.replace('_', ' ') : '',
                        length: 280,
                        arrows: {
                            to: { enabled: true, scaleFactor: 0.85 }
                        },
                        color: {
                            color: '#64748b',
                            highlight: '#38bdf8',
                            hover: '#94a3b8'
                        },
                        font: {
                            color: '#e2e8f0',
                            size: 11,
                            face: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
                            align: 'middle',
                            background: 'rgba(15, 23, 42, 0.85)',
                            strokeWidth: 0
                        },
                        smooth: {
                            type: 'continuous',
                            roundness: 0.2
                        },
                        width: 2
                    };
                },

                initVisNetwork(nodesRaw, edgesRaw) {
                    const container = this.$refs.networkContainer;
                    if (!container || typeof vis === 'undefined') return;

                    const formattedNodes = (nodesRaw || []).map(n => this.formatNode(n));
                    const formattedEdges = (edgesRaw || []).map((e, idx) => this.formatEdge(e, idx));

                    this.nodesDataSet = new vis.DataSet(formattedNodes);
                    this.edgesDataSet = new vis.DataSet(formattedEdges);

                    const data = {
                        nodes: this.nodesDataSet,
                        edges: this.edgesDataSet
                    };

                    const options = {
                        autoResize: true,
                        interaction: {
                            hover: true,
                            tooltipDelay: 100,
                            zoomView: true,
                            dragView: true
                        },
                        physics: {
                            enabled: true,
                            solver: 'barnesHut',
                            barnesHut: {
                                gravitationalConstant: -18000,
                                centralGravity: 0.08,
                                springLength: 280,
                                springConstant: 0.035,
                                damping: 0.35,
                                avoidOverlap: 0.95
                            },
                            stabilization: {
                                iterations: 200,
                                updateInterval: 25
                            }
                        }
                    };

                    this.network = new vis.Network(container, data, options);

                    // Al estabilizarse, auto-centrar con zoom cómodo
                    this.network.once('stabilizationIterationsDone', () => {
                        this.fitNetwork();
                    });
                    setTimeout(() => this.fitNetwork(), 400);

                    // Al hacer clic en un nodo
                    this.network.on('click', (params) => {
                        if (params.nodes.length > 0) {
                            const nodeId = params.nodes[0];
                            this.$wire.selectNode(nodeId);
                        } else {
                            this.$wire.selectNode(null);
                        }
                    });

                    // Cursor de puntero al pasar sobre nodos
                    this.network.on('hoverNode', () => {
                        container.style.cursor = 'pointer';
                    });
                    this.network.on('blurNode', () => {
                        container.style.cursor = 'grab';
                    });
                },

                updateTopologyData(newNodes, newEdges) {
                    if (!this.nodesDataSet || !this.edgesDataSet) return;

                    const currentIds = this.nodesDataSet.getIds();
                    const newFormattedNodes = (newNodes || []).map(n => this.formatNode(n));
                    const newIds = newFormattedNodes.map(n => n.id);

                    // Eliminar nodos que ya no existan
                    const toRemove = currentIds.filter(id => !newIds.includes(id));
                    if (toRemove.length > 0) {
                        this.nodesDataSet.remove(toRemove);
                    }

                    // Actualizar o añadir nodos
                    this.nodesDataSet.update(newFormattedNodes);

                    // Actualizar conexiones
                    const formattedEdges = (newEdges || []).map((e, idx) => this.formatEdge(e, idx));
                    this.edgesDataSet.clear();
                    this.edgesDataSet.add(formattedEdges);
                },

                fitNetwork() {
                    if (this.network) {
                        this.network.fit({
                            animation: {
                                duration: 500,
                                easingFunction: 'easeInOutQuad'
                            }
                        });
                    }
                },

                togglePhysics() {
                    this.physicsEnabled = !this.physicsEnabled;
                    if (this.network) {
                        this.network.setOptions({
                            physics: { enabled: this.physicsEnabled }
                        });
                    }
                }
            }));
        });
    </script>
    @endpush
</x-filament-panels::page>
