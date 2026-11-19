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
use Illuminate\Support\Facades\Log;

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
            $this->syncBackendHealth();
        }

        $topology = InfraTopology::find('current');

        if ($topology) {
            $this->loadFromSnapshot($topology);
        } else {
            $this->loadFromDynamicAgents();
        }

        $this->refreshSelectedNode();

        $this->dispatch('topology-updated', [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
        ]);
    }

    /**
     * Sincroniza la salud del backend mediante AgentHttpClient.
     */
    private function syncBackendHealth(): void
    {
        try {
            $client = app(AgentHttpClient::class);
            $client->checkHealth();
        } catch (\Throwable $e) {
            Log::debug('[TopologyMap] Comprobación de salud omitida o fallida: '.$e->getMessage());
        }
    }

    /**
     * Carga topología desde la instantánea persistida.
     */
    private function loadFromSnapshot(InfraTopology $topology): void
    {
        $this->nodes = is_array($topology->nodes_json) ? $topology->nodes_json : [];
        $this->edges = is_array($topology->edges_json) ? $topology->edges_json : [];
        $this->lastUpdated = $topology->updated_at ? $topology->updated_at->diffForHumans() : 'Recientemente';
        $this->totalNodes = $topology->nodes_count;
        $this->healthyNodes = $topology->healthy_count;
        $this->downNodes = $topology->down_count;
        $this->warningNodes = $topology->warning_count;
        $this->totalEdges = $topology->edges_count;
    }

    /**
     * Construye topología dinámica a partir de agentes registrados y contenedores base.
     */
    private function loadFromDynamicAgents(): void
    {
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

            $mcpElements = $this->buildMcpNodesForAgent($agent, $isHealthy);
            $agentNodes = array_merge($agentNodes, $mcpElements['nodes']);
            $agentEdges = array_merge($agentEdges, $mcpElements['edges']);
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

    /**
     * Extrae nodos y aristas para sidecars MCP asociados al agente.
     *
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function buildMcpNodesForAgent(Agent $agent, bool $isHealthy): array
    {
        $nodes = [];
        $edges = [];

        $mcpSidecars = $agent->metrics_json['mcp_sidecars']
            ?? $agent->capabilities_json['mcp_servers']
            ?? [];

        if (!is_array($mcpSidecars)) {
            return ['nodes' => $nodes, 'edges' => $edges];
        }

        foreach ($mcpSidecars as $key => $item) {
            $mcpData = is_array($item) ? $item : [];
            $mcpName = is_string($key) && !is_numeric($key) ? $key : ($mcpData['name'] ?? (string) $key);
            $mcpStatus = $mcpData['status'] ?? ($isHealthy ? 'connected' : 'disconnected');
            $mcpUrl = $mcpData['url'] ?? ($mcpData['endpoint'] ?? 'http://internal/mcp');
            $mcpToolsCount = $mcpData['tools_count'] ?? (isset($mcpData['tools']) && is_array($mcpData['tools']) ? count($mcpData['tools']) : 0);
            $mcpSchemaHash = $mcpData['schema_hash'] ?? ($mcpData['hash'] ?? 'n/a');

            $mcpNodeId = "mcp_{$agent->agent_id}_{$mcpName}";
            $nodes[] = [
                'id' => $mcpNodeId,
                'label' => "MCP: {$mcpName}",
                'type' => 'mcp_server',
                'status' => $mcpStatus === 'connected' ? 'healthy' : 'down',
                'metadata' => [
                    'transport' => $mcpData['transport'] ?? 'Streamable HTTP',
                    'url' => $mcpUrl,
                    'tools_count' => $mcpToolsCount,
                    'schema_hash' => $mcpSchemaHash,
                ],
            ];
            $edges[] = [
                'from' => $agent->agent_id,
                'to' => $mcpNodeId,
                'relation' => 'mcp_streamable_http',
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Refresca los datos del nodo actualmente seleccionado si aún existe.
     */
    private function refreshSelectedNode(): void
    {
        if ($this->selectedNode) {
            $nodeId = $this->selectedNode['id'] ?? null;
            $found = collect($this->nodes)->firstWhere('id', $nodeId);
            if ($found) {
                $this->selectedNode = $found;
            }
        }
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
