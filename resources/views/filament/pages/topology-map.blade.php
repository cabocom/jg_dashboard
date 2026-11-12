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

            @include('filament.pages.partials._topology_sidebar')
        </div>

        @include('filament.pages.partials._topology_metrics')
    </div>

    @include('filament.pages.partials._topology_scripts')
</x-filament-panels::page>
