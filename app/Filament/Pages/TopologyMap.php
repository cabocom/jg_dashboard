<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Filament\Pages;

use App\Models\Agent;
use App\Models\InfraTopology;
use App\Services\Agents\AgentHttpClient;
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
     * Refresca los datos de la topología desde PostgreSQL o modelos Eloquent de agentes.
     */
    public function refreshTopology(bool $forceBackendSync = false): void
    {
        if ($forceBackendSync) {
            try {
                $client = app(AgentHttpClient::class);
                $client->checkHealth();
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
            // Nodos dinámicos a partir de agentes registrados y contenedores base
            $agents = Agent::all();
            $agentNodes = [];
            $agentEdges = [];

            foreach ($agents as $agent) {
                $isHealthy = $agent->status === 'ACTIVE' && $agent->isHealthy(90);
                $nodeStatus = $isHealthy ? 'healthy' : ($agent->status === 'PENDING' ? 'warning' : 'down');
                $agentNodes[] = [
                    'id' => $agent->agent_id,
                    'label' => $agent->name,
                    'type' => 'container',
                    'status' => $nodeStatus,
                    'metadata' => [
                        'version' => $agent->version ?? '1.0.0',
                        'last_heartbeat' => $agent->last_heartbeat_at?->toIso8601String(),
                        'api_url' => $agent->metadata_json['api_url'] ?? 'internal',
                    ],
                ];
                $agentEdges[] = [
                    'from' => 'jg_dashboard',
                    'to' => $agent->agent_id,
                    'relation' => 'rest_api',
                ];
            }

            $baseNodes = [
                ['id' => 'host', 'label' => 'Host del Sistema', 'type' => 'host', 'status' => 'healthy', 'metadata' => ['os' => 'Linux / Docker']],
                ['id' => 'jg_dashboard', 'label' => 'jg_dashboard', 'type' => 'container', 'status' => 'healthy', 'metadata' => ['image' => 'jg_dashboard:latest', 'state' => 'running']],
                ['id' => 'jg_dashboard_postgres', 'label' => 'jg_dashboard_postgres', 'type' => 'database', 'status' => 'healthy', 'metadata' => ['image' => 'postgres:16-alpine', 'state' => 'running']],
                ['id' => 'jg_agente_postgres', 'label' => 'jg_agente_postgres', 'type' => 'database', 'status' => 'healthy', 'metadata' => ['image' => 'postgres:16-alpine', 'state' => 'running']],
            ];

            $baseEdges = [
                ['from' => 'host', 'to' => 'jg_dashboard', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'jg_dashboard_postgres', 'relation' => 'contains'],
                ['from' => 'host', 'to' => 'jg_agente_postgres', 'relation' => 'contains'],
                ['from' => 'jg_dashboard', 'to' => 'jg_dashboard_postgres', 'relation' => 'database_connection'],
            ];

            $this->nodes = array_merge($baseNodes, $agentNodes);
            $this->edges = array_merge($baseEdges, $agentEdges);

            $this->lastUpdated = 'Dinámico (Eloquent Agent)';
            $this->totalNodes = count($this->nodes);
            $this->healthyNodes = collect($this->nodes)->where('status', 'healthy')->count();
            $this->downNodes = collect($this->nodes)->where('status', 'down')->count();
            $this->warningNodes = collect($this->nodes)->where('status', 'warning')->count();
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
