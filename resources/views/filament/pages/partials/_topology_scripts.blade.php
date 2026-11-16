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
                } else if (type === 'mcp_server') {
                    bgColor = '#312e81';
                    borderColor = '#6366f1';
                    fontColor = '#e0e7ff';
                    shape = 'box';
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

                this.network.once('stabilizationIterationsDone', () => {
                    this.fitNetwork();
                });
                setTimeout(() => this.fitNetwork(), 400);

                this.network.on('click', (params) => {
                    if (params.nodes.length > 0) {
                        const nodeId = params.nodes[0];
                        this.$wire.selectNode(nodeId);
                    } else {
                        this.$wire.selectNode(null);
                    }
                });

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

                const toRemove = currentIds.filter(id => !newIds.includes(id));
                if (toRemove.length > 0) {
                    this.nodesDataSet.remove(toRemove);
                }

                this.nodesDataSet.update(newFormattedNodes);

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

