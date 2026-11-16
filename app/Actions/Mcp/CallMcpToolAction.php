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
use App\Models\ApiToken;
use App\Models\AuditEvent;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Support\Str;

class CallMcpToolAction
{
    /**
     * Despacha la llamada MCP hacia el endpoint determinista del agente correspondiente.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $fullName, array $arguments = [], ?ApiToken $apiToken = null): array
    {
        if (! str_contains($fullName, '__')) {
            return [
                'code' => -32601,
                'message' => "Invalid tool format. Expected '{agent}__{tool}', received '{$fullName}'",
            ];
        }

        [$agentKey, $toolName] = explode('__', $fullName, 2);

        $agent = $this->findAgent($agentKey);
        if (! $agent) {
            return [
                'code' => -32601,
                'message' => "Agent '{$agentKey}' not found or inactive",
            ];
        }

        $policyCheck = $this->verifyToolPolicy($agent, $toolName);
        if ($policyCheck !== null) {
            return $policyCheck;
        }

        return $this->invokeAgentTool($agent, $toolName, $fullName, $arguments, $apiToken);
    }

    /**
     * Localiza el agente activo por slug, agent_id o nombre.
     */
    protected function findAgent(string $agentKey): ?Agent
    {
        return Agent::whereIn('status', ['ACTIVE', 'active'])->get()->first(function (Agent $candidate) use ($agentKey) {
            return $candidate->agent_id === $agentKey
                || Str::slug($candidate->name, '_') === $agentKey
                || strtolower($candidate->name) === strtolower($agentKey);
        });
    }

    /**
     * Valida que la herramienta exista y cumpla la política v1 de bajo riesgo y lectura.
     *
     * @return array{code: int, message: string}|null
     */
    protected function verifyToolPolicy(Agent $agent, string $toolName): ?array
    {
        $capabilities = $agent->capabilities_json ?? [];
        $tools = $capabilities['tools'] ?? [];
        $toolDef = collect($tools)->firstWhere('name', $toolName);

        if (! $toolDef) {
            return [
                'code' => -32601,
                'message' => "Tool '{$toolName}' not registered on agent '{$agent->name}'",
            ];
        }

        $risk = strtolower((string) ($toolDef['risk_level'] ?? 'low'));
        $readOnly = (bool) ($toolDef['read_only'] ?? false);

        if ($risk !== 'low' || ! $readOnly) {
            return [
                'code' => -32601,
                'message' => "Tool '{$toolName}' exceeds MCP v1 low-risk read-only policy",
            ];
        }

        return null;
    }

    protected function resolveHttpClient(Agent $agent): AgentHttpClient
    {
        if (app()->bound(AgentHttpClient::class)) {
            return app(AgentHttpClient::class);
        }

        return AgentHttpClient::forAgent($agent);
    }

    /**
     * Invoca la herramienta vía HTTP firmada con HMAC hacia el runtime jg_agente.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function invokeAgentTool(
        Agent $agent,
        string $toolName,
        string $fullName,
        array $arguments,
        ?ApiToken $apiToken
    ): array {
        $client = $this->resolveHttpClient($agent);
        $payload = [
            'arguments' => $arguments,
            'execution_id' => Str::uuid()->toString(),
        ];

        $response = $client->sendRequest('POST', "/tools/{$toolName}/execute", $payload);

        if (! $response['success'] || $response['status_code'] >= 400) {
            $errorMsg = $response['data']['detail']['message'] ?? $response['error'] ?? "Execution failed on {$agent->name}";
            AuditEvent::record('mcp_tool_failed', "MCP tool '{$fullName}' failed: {$errorMsg}", [
                'arguments' => $arguments,
                'error' => $errorMsg,
            ], $apiToken?->user, $agent);

            return [
                'content' => [
                    ['type' => 'text', 'text' => "Error: {$errorMsg}"],
                ],
                'isError' => true,
            ];
        }

        $output = $response['data']['output'] ?? '';
        $outputText = is_string($output) ? $output : json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        AuditEvent::record('mcp_tool_called', "MCP tool '{$fullName}' executed successfully", [
            'arguments' => $arguments,
        ], $apiToken?->user, $agent);

        return [
            'content' => [
                ['type' => 'text', 'text' => $outputText],
            ],
            'isError' => false,
        ];
    }
}
