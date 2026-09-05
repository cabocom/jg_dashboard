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
            // Fallback con nodos locales si aún no hay descubrimiento guardado
            $this->nodes = [
                ['id' => 'host', 'label' => 'Host SRE System', 'type' => 'host', 'status' => 'healthy', 'metadata' => ['os' => 'Linux / Docker']],
                ['id' => 'devops60k_agent', 'label' => 'devops60k_agent', 'type' => 'container', 'status' => 'healthy', 'metadata' => ['image' => 'devops60k:latest', 'state' => 'running']],
                ['id' => 'devops60k_web', 'label' => 'devops60k_web', 'type' => 'container', 'status' => 'healthy', 'metadata' => ['image' => 'devops60k-web:latest', 'state' => 'running']],
                ['id' => 'devops60k_postgres', 'label' => 'devops60k_postgres', 'type' => 'database', 'status' => 'healthy', 'metadata' => ['image' => 'postgres:16-alpine', 'state' => 'running']],
                ['id' => 'devops60k_docker_proxy', 'label' => 'devops60k_docker_proxy', 'type' => 'container', 'status' => 'healthy', 'metadata' => ['image' => 'docker-socket-proxy:latest', 'state' => 'running']],
            ];
            $this->edges = [
                ['from' => 'host', 'to' => 'devops60k_agent', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'devops60k_web', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'devops60k_postgres', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'devops60k_docker_proxy', 'relation' => 'contains'],
                ['from' => 'devops60k_agent', 'to' => 'devops60k_postgres', 'relation' => 'database_connection'],
                ['from' => 'devops60k_web', 'to' => 'devops60k_postgres', 'relation' => 'database_connection'],
                ['from' => 'devops60k_agent', 'to' => 'devops60k_docker_proxy', 'relation' => 'socket_proxy'],
                ['from' => 'devops60k_web', 'to' => 'devops60k_agent', 'relation' => 'rest_api'],
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
