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

