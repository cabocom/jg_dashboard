<?php

namespace App\Filament\Pages;

use App\Models\InfraTopology;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class TopologyMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'Mapa de Topología';
    protected static ?string $title = 'Mapa de Topología de Infraestructura';
    protected static ?string $navigationGroup = 'Observabilidad';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.topology-map';

    public array $nodes = [];
    public array $edges = [];
    public ?string $lastUpdated = null;
    public ?array $selectedNode = null;
    public int $totalNodes = 0;
    public int $healthyNodes = 0;
    public int $downNodes = 0;
    public int $warningNodes = 0;
    public int $totalEdges = 0;

    public function mount(): void
    {
        $this->refreshTopology();
    }

    /**
     * Refresca los datos de la topología desde PostgreSQL.
     */
    public function refreshTopology(bool $forceBackendSync = false): void
    {
        if ($forceBackendSync) {
            try {
                app(\App\Services\DevOps60kApiClient::class)->getTopology(refresh: true);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $topology = InfraTopology::find('current');

        if ($topology) {
            $this->nodes = is_array($topology->nodes_json) ? $topology->nodes_json : [];
            $this->edges = is_array($topology->edges_json) ? $topology->edges_json : [];
            $this->lastUpdated = $topology->updated_at ? $topology->updated_at->diffForHumans() : 'Recientemente';
            $this->totalNodes = $topology->nodes_count;
            $this->healthyNodes = $topology->healthy_count;
            $this->downNodes = $topology->down_count;
            $this->warningNodes = $topology->warning_count;
            $this->totalEdges = $topology->edges_count;
        } else {
            // Fallback con nodos locales de la arquitectura jg_agente
            $this->nodes = [
                ['id' => 'host', 'label' => 'Host SRE System', 'type' => 'host', 'status' => 'healthy', 'metadata' => ['os' => 'Linux / Docker']],
                ['id' => 'jg_dashboard', 'label' => 'jg_dashboard', 'type' => 'container', 'status' => 'healthy', 'metadata' => ['image' => 'jg_dashboard:latest', 'state' => 'running']],
                ['id' => 'jg_dashboard_postgres', 'label' => 'jg_dashboard_postgres', 'type' => 'database', 'status' => 'healthy', 'metadata' => ['image' => 'postgres:16-alpine', 'state' => 'running']],
                ['id' => 'jg_agente_conversacional', 'label' => 'jg_agente_conversacional', 'type' => 'container', 'status' => 'healthy', 'metadata' => ['image' => 'jg_agente:latest', 'state' => 'running', 'plugin' => 'conversational']],
                ['id' => 'jg_agente_devops', 'label' => 'jg_agente_devops', 'type' => 'container', 'status' => 'healthy', 'metadata' => ['image' => 'jg_agente:latest', 'state' => 'running', 'plugin' => 'devops']],
                ['id' => 'jg_agente_postgres', 'label' => 'jg_agente_postgres', 'type' => 'database', 'status' => 'healthy', 'metadata' => ['image' => 'postgres:16-alpine', 'state' => 'running']],
            ];
            $this->edges = [
                ['from' => 'host', 'to' => 'jg_dashboard', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'jg_dashboard_postgres', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'jg_agente_conversacional', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'jg_agente_devops', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'jg_agente_postgres', 'relation' => 'contains'],
                ['from' => 'jg_dashboard', 'to' => 'jg_dashboard_postgres', 'relation' => 'database_connection'],
                ['from' => 'jg_agente_conversacional', 'to' => 'jg_agente_postgres', 'relation' => 'database_connection'],
                ['from' => 'jg_agente_devops', 'to' => 'jg_agente_postgres', 'relation' => 'database_connection'],
                ['from' => 'jg_dashboard', 'to' => 'jg_agente_conversacional', 'relation' => 'rest_api'],
                ['from' => 'jg_dashboard', 'to' => 'jg_agente_devops', 'relation' => 'rest_api'],
                ['from' => 'jg_agente_devops', 'to' => 'host', 'relation' => 'docker_socket'],
            ];
            $this->lastUpdated = 'Por defecto';
            $this->totalNodes = count($this->nodes);
            $this->healthyNodes = $this->totalNodes;
            $this->downNodes = 0;
            $this->warningNodes = 0;
            $this->totalEdges = count($this->edges);
        }

        // Si hay un nodo seleccionado, refrescar sus datos
        if ($this->selectedNode) {
            $nodeId = $this->selectedNode['id'] ?? null;
            $found = collect($this->nodes)->firstWhere('id', $nodeId);
            if ($found) {
                $this->selectedNode = $found;
            }
        }

        $this->dispatch('topology-updated', [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
        ]);
    }

    /**
     * Selecciona un nodo por ID para ver sus metadatos en el slide-over modal.
     */
    public function selectNode(?string $nodeId): void
    {
        if (!$nodeId) {
            $this->selectedNode = null;
            return;
        }

        $node = collect($this->nodes)->firstWhere('id', $nodeId);
        $this->selectedNode = $node ?: null;
    }

    /**
     * Cierra el panel de detalles del nodo.
     */
    public function closeNodeDetails(): void
    {
        $this->selectedNode = null;
    }
}
