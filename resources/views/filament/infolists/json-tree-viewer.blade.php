@php
    $entryRecord = isset($getRecord) && is_callable($getRecord) ? $getRecord() : ($record ?? null);
    
    $fieldName = $field ?? 'result_json';
    $rawState = $getState() ?? ($entryRecord ? $entryRecord->{$fieldName} : null);

    if (is_string($rawState)) {
        $decoded = json_decode($rawState, true);
        $jsonData = json_last_error() === JSON_ERROR_NONE ? $decoded : $rawState;
    } else {
        $jsonData = $rawState;
    }

    $isEmpty = empty($jsonData);
@endphp

<div class="w-full space-y-3" x-data="jsonTreeViewer({
    rawData: @js($jsonData),
    initiallyExpandedDepth: 2
})">
    @if($isEmpty)
        <div class="text-xs text-gray-500 dark:text-gray-400 italic p-4 bg-gray-100 dark:bg-gray-900/60 rounded-xl border border-gray-200 dark:border-gray-800">
            // Sin datos registrados
        </div>
    @else
        <!-- Barra de herramientas del visor JSON -->
        <div class="flex flex-wrap items-center justify-between gap-2 p-2 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl text-xs">
            <div class="flex items-center space-x-2">
                <button type="button" @click="expandAll()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg shadow-sm transition hover:bg-gray-50 dark:hover:bg-gray-700">
                    <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-emerald-500" />
                    Expandir Todo
                </button>
                <button type="button" @click="collapseAll()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg shadow-sm transition hover:bg-gray-50 dark:hover:bg-gray-700">
                    <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-amber-500" />
                    Colapsar Todo
                </button>
                <button type="button" @click="toggleViewMode()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg shadow-sm transition hover:bg-gray-50 dark:hover:bg-gray-700">
                    <x-heroicon-m-code-bracket class="w-3.5 h-3.5 text-sky-500" />
                    <span x-text="viewMode === 'tree' ? 'Ver JSON Crudo' : 'Ver Árbol'"></span>
                </button>
            </div>

            <div class="flex items-center space-x-2">
                <!-- Buscador rápido dentro del JSON -->
                <div class="relative" x-show="viewMode === 'tree'">
                    <input type="text" x-model="searchQuery" placeholder="Filtrar claves o valores..." class="w-52 px-3 py-1 text-xs bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute right-2 top-1.5 text-gray-400 hover:text-gray-200">
                        <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Botón Copiar -->
                <button type="button" @click="copyJson()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg shadow-sm transition hover:bg-gray-50 dark:hover:bg-gray-700">
                    <x-heroicon-m-clipboard-document class="w-3.5 h-3.5 text-gray-500" />
                    <span x-text="copied ? '¡Copiado!' : 'Copiar JSON'"></span>
                </button>
            </div>
        </div>

        <!-- 1. Vista en Modo Árbol Interactivo (Fondo Oscuro de Alto Contraste Forzado) -->
        <div x-show="viewMode === 'tree'" style="background-color: #0b0f19 !important; color: #f8fafc !important; border: 1px solid #1e293b; min-height: 260px;" class="p-4 rounded-xl font-mono text-xs overflow-x-auto shadow-2xl">
            <div id="json-tree-root-container" class="space-y-1 select-text">
                <template x-if="parsedData !== null && parsedData !== undefined">
                    <div x-html="renderTreeHtml(parsedData, '', 0)"></div>
                </template>
            </div>
        </div>

        <!-- 2. Vista en Modo Crudo (Raw formatted con fondo oscuro de alto contraste) -->
        <div x-show="viewMode === 'raw'" style="background-color: #0b0f19 !important; color: #34d399 !important; border: 1px solid #1e293b; min-height: 260px;" class="p-4 rounded-xl font-mono text-xs overflow-x-auto whitespace-pre shadow-2xl">
            <code x-text="formattedJson" style="color: #34d399 !important; font-family: ui-monospace, monospace;"></code>
        </div>
    @endif
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('jsonTreeViewer', (config) => ({
            rawData: config.rawData,
            parsedData: null,
            viewMode: 'tree',
            searchQuery: '',
            copied: false,
            collapsedNodes: {},

            init() {
                try {
                    this.parsedData = typeof this.rawData === 'string' ? JSON.parse(this.rawData) : this.rawData;
                } catch (e) {
                    this.parsedData = this.rawData;
                }
            },

            get formattedJson() {
                try {
                    return JSON.stringify(this.parsedData, null, 2);
                } catch (e) {
                    return String(this.parsedData);
                }
            },

            toggleViewMode() {
                this.viewMode = this.viewMode === 'tree' ? 'raw' : 'tree';
            },

            copyJson() {
                navigator.clipboard.writeText(this.formattedJson).then(() => {
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2000);
                });
            },

            toggleNode(path) {
                this.collapsedNodes[path] = !this.collapsedNodes[path];
                this.$nextTick(() => {
                    const el = document.getElementById('node-children-' + path);
                    const btn = document.getElementById('node-btn-' + path);
                    const preview = document.getElementById('node-preview-' + path);
                    if (el && btn) {
                        if (this.collapsedNodes[path]) {
                            el.style.display = 'none';
                            btn.innerHTML = '&#9654;';
                            if (preview) preview.style.display = 'inline-block';
                        } else {
                            el.style.display = 'block';
                            btn.innerHTML = '&#9660;';
                            if (preview) preview.style.display = 'none';
                        }
                    }
                });
            },

            expandAll() {
                this.collapsedNodes = {};
                document.querySelectorAll('[id^="node-children-"]').forEach(el => {
                    el.style.display = 'block';
                });
                document.querySelectorAll('[id^="node-btn-"]').forEach(el => {
                    el.innerHTML = '&#9660;';
                });
                document.querySelectorAll('[id^="node-preview-"]').forEach(el => {
                    el.style.display = 'none';
                });
            },

            collapseAll() {
                document.querySelectorAll('[id^="node-children-"]').forEach(el => {
                    const path = el.id.replace('node-children-', '');
                    this.collapsedNodes[path] = true;
                    el.style.display = 'none';
                });
                document.querySelectorAll('[id^="node-btn-"]').forEach(el => {
                    el.innerHTML = '&#9654;';
                });
                document.querySelectorAll('[id^="node-preview-"]').forEach(el => {
                    el.style.display = 'inline-block';
                });
            },

            escapeHtml(str) {
                if (typeof str !== 'string') str = String(str);
                return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            },

            renderTreeHtml(val, currentPath, depth) {
                if (val === null) {
                    return `<span style="color: #94a3b8; font-style: italic;">null</span>`;
                }

                const type = typeof val;

                if (type === 'boolean') {
                    return `<span style="color: #c084fc; font-weight: 600;">${val}</span>`;
                }

                if (type === 'number') {
                    return `<span style="color: #fbbf24; font-weight: bold;">${val}</span>`;
                }

                if (type === 'string') {
                    const esc = this.escapeHtml(val);
                    return `<span style="color: #34d399;">&quot;${esc}&quot;</span>`;
                }

                const isArray = Array.isArray(val);
                const isObject = type === 'object';

                if (isArray || isObject) {
                    const keys = Object.keys(val);
                    const length = keys.length;
                    const path = currentPath || 'root';
                    const isCollapsed = this.collapsedNodes[path] || (depth >= 2 && this.collapsedNodes[path] === undefined);
                    
                    if (length === 0) {
                        return isArray ? `<span style="color: #94a3b8;">[]</span>` : `<span style="color: #94a3b8;">{}</span>`;
                    }

                    const openBracket = isArray ? '[' : '{';
                    const closeBracket = isArray ? ']' : '}';
                    const typeLabel = isArray ? `Array(${length})` : `Object(${length})`;

                    let html = `<div style="display: block; width: 100%;">`;
                    
                    // Cabecera del nodo con botón toggle
                    html += `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 0;">`;
                    html += `<button type="button" id="node-btn-${path}" onclick="Alpine.$data(this).toggleNode('${path}')" style="width: 16px; height: 16px; display: inline-flex; align-items: center; justify-content: center; color: #94a3b8; background: transparent; border: none; font-size: 10px; cursor: pointer; user-select: none;">${isCollapsed ? '&#9654;' : '&#9660;'}</button>`;
                    html += `<span style="color: #e2e8f0; font-family: ui-monospace, monospace;">${openBracket}</span>`;
                    html += `<span id="node-preview-${path}" style="display: ${isCollapsed ? 'inline-block' : 'none'}; padding: 1px 6px; border-radius: 4px; font-size: 10px; background-color: #1e293b; color: #cbd5e1; border: 1px solid #334155; margin-left: 4px;">... ${typeLabel}</span>`;
                    html += `</span>`;

                    // Contenedor de hijos
                    html += `<div id="node-children-${path}" style="display: ${isCollapsed ? 'none' : 'block'}; padding-left: 18px; margin-left: 6px; border-left: 1px solid #1e293b; padding-top: 2px; padding-bottom: 2px;">`;

                    for (let i = 0; i < keys.length; i++) {
                        const k = keys[i];
                        const childVal = val[k];
                        const childPath = `${path}_${k}`;
                        const childHtml = this.renderTreeHtml(childVal, childPath, depth + 1);
                        const isKeyMatch = this.searchQuery && String(k).toLowerCase().includes(this.searchQuery.toLowerCase());

                        html += `<div style="display: flex; align-items: flex-start; padding: 2px 4px; border-radius: 4px; ${isKeyMatch ? 'background-color: rgba(180, 83, 9, 0.3); outline: 1px solid #f59e0b;' : ''}">`;
                        
                        if (!isArray) {
                            html += `<span style="color: #38bdf8; font-weight: 600; margin-right: 4px; user-select: text;">&quot;${this.escapeHtml(k)}&quot;</span><span style="color: #94a3b8; margin-right: 6px;">:</span>`;
                        } else {
                            html += `<span style="color: #64748b; font-size: 11px; margin-right: 6px; user-select: none;">${k}:</span>`;
                        }

                        html += `<div style="display: inline-block;">${childHtml}</div>`;
                        if (i < keys.length - 1) {
                            html += `<span style="color: #64748b;">,</span>`;
                        }
                        html += `</div>`;
                    }

                    html += `</div>`;
                    html += `<span style="color: #e2e8f0; font-family: ui-monospace, monospace;">${closeBracket}</span>`;
                    html += `</div>`;

                    return html;
                }

                return `<span style="color: #f1f5f9;">${this.escapeHtml(String(val))}</span>`;
            }
        }));
    });
</script>
