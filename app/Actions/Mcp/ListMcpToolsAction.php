<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Actions\Mcp;

use App\Models\Agent;
use Illuminate\Support\Str;
use stdClass;

class ListMcpToolsAction
{
    /**
     * Construye el inventario de herramientas MCP con nombres con namespace y política estricta v1.
     *
     * @return array{tools: array<int, array{name: string, description: string, inputSchema: mixed}>}
     */
    public function execute(): array
    {
        $agents = Agent::whereIn('status', ['ACTIVE', 'active'])->get();
        $tools = [];

        foreach ($agents as $agent) {
            $agentSlug = Str::slug($agent->name, '_');
            if (empty($agentSlug)) {
                $agentSlug = str_replace('-', '_', $agent->agent_id);
            }

            $capabilities = $agent->capabilities_json ?? [];
            $agentTools = $capabilities['tools'] ?? [];

            foreach ($agentTools as $tool) {
                if (! $this->isToolEligibleForMcpV1($tool)) {
                    continue;
                }

                $toolName = (string) ($tool['name'] ?? '');
                if (empty($toolName)) {
                    continue;
                }

                $tools[] = [
                    'name' => "{$agentSlug}__{$toolName}",
                    'description' => (string) ($tool['description'] ?? ''),
                    'inputSchema' => $this->resolveInputSchema($tool),
                ];
            }
        }

        return ['tools' => $tools];
    }

    /**
     * Filtro de seguridad estricto v1: solo herramientas de bajo riesgo y solo lectura.
     *
     * @param  array<string, mixed>  $tool
     */
    protected function isToolEligibleForMcpV1(array $tool): bool
    {
        $risk = strtolower((string) ($tool['risk_level'] ?? 'low'));
        $readOnly = (bool) ($tool['read_only'] ?? false);

        return $risk === 'low' && $readOnly === true;
    }

    /**
     * Resuelve el esquema OpenAPI / JSON Schema de argumentos.
     *
     * @param  array<string, mixed>  $tool
     */
    protected function resolveInputSchema(array $tool): mixed
    {
        if (! empty($tool['input_schema']) && is_array($tool['input_schema'])) {
            return $tool['input_schema'];
        }

        if (! empty($tool['inputSchema']) && is_array($tool['inputSchema'])) {
            return $tool['inputSchema'];
        }

        return [
            'type' => 'object',
            'properties' => new stdClass,
        ];
    }
}
