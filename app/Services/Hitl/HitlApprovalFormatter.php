<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Hitl;

use App\Models\ApprovalRequest;

class HitlApprovalFormatter
{
    /**
     * Extract a human-readable action summary from the proposed plan.
     */
    public function extractActionSummary(ApprovalRequest $approval): string
    {
        $plan = $approval->proposed_plan_json ?? [];

        if (! empty($plan['summary']) && is_string($plan['summary'])) {
            return $plan['summary'];
        }

        if (! empty($plan['action']) && is_string($plan['action'])) {
            return $plan['action'];
        }

        if (! empty($plan['description']) && is_string($plan['description'])) {
            return $plan['description'];
        }

        if (! empty($plan['command']) && is_string($plan['command'])) {
            return "Ejecución de comando: {$plan['command']}";
        }

        if (! empty($plan['step']) && is_string($plan['step'])) {
            return "Paso del plan: {$plan['step']}";
        }

        $tools = $plan['tools_involved'] ?? ($plan['tools'] ?? []);
        if (! empty($tools) && is_array($tools)) {
            return 'Ejecución de herramientas: '.implode(', ', $tools);
        }

        return 'Plan de ejecución pendiente de revisión humana';
    }

    /**
     * Format an approval request into a standardized API payload.
     *
     * @return array<string, mixed>
     */
    public function formatApprovalItem(ApprovalRequest $approval): array
    {
        $plan = $approval->proposed_plan_json ?? [];
        $prompt = $plan['prompt'] ?? null;
        $cognitiveSteps = $plan['cognitive_steps'] ?? [];

        $tools = $plan['tools_involved'] ?? ($plan['tools'] ?? []);
        $planSteps = is_array($plan['steps'] ?? null) ? $plan['steps'] : [];

        // Si plan.steps está vacío, extraer los pasos de risk_assessment.plan_steps o tool_calls en cognitive_steps
        if (empty($planSteps) && is_array($cognitiveSteps)) {
            foreach ($cognitiveSteps as $cs) {
                $raSteps = $cs['details']['risk_assessment']['plan_steps'] ?? null;
                if (is_array($raSteps) && ! empty($raSteps)) {
                    $planSteps = $raSteps;
                    break;
                }
            }
        }
        if (empty($planSteps) && is_array($cognitiveSteps)) {
            foreach ($cognitiveSteps as $cs) {
                $tcs = $cs['details']['tool_calls'] ?? null;
                if (is_array($tcs) && ! empty($tcs)) {
                    foreach ($tcs as $idx => $tc) {
                        $tName = (string) ($tc['name'] ?? ($tc['tool_name'] ?? 'tool'));
                        $tArgs = is_array($tc['args'] ?? null) ? $tc['args'] : [];
                        $cmd = $tArgs['command'] ?? ($tArgs['target_url'] ?? ($tArgs['url'] ?? ($tArgs['target'] ?? null)));
                        $isMut = preg_match('/(mutate|restart|stop|rm|delete|destroy|create|run|kill|write|update)/i', $tName.' '.(string) $cmd) === 1;
                        $planSteps[] = [
                            'index' => $idx + 1,
                            'step_id' => $tc['id'] ?? 'step_'.($idx + 1),
                            'tool_name' => $tName,
                            'tool' => $tName,
                            'command' => $cmd,
                            'action' => $cmd ? "Ejecutar {$tName}: {$cmd}" : "Ejecutar {$tName}",
                            'description' => $cmd ? "Ejecutar {$tName}: {$cmd}" : "Ejecutar {$tName}",
                            'risk_level' => $isMut ? 'HIGH' : 'LOW',
                            'mutative' => $isMut,
                            'read_only' => ! $isMut,
                        ];
                    }
                    break;
                }
            }
        }

        if (! empty($planSteps)) {
            $plan['steps'] = array_values($planSteps);
            if (empty($tools)) {
                $tools = array_values(array_unique(array_filter(array_map(
                    fn ($s) => is_array($s) ? ($s['tool_name'] ?? ($s['tool'] ?? null)) : null,
                    $planSteps
                ))));
            }
        }

        $mutativeCount = 0;
        foreach ($plan['steps'] ?? [] as $s) {
            if (is_array($s)) {
                $isMut = ($s['mutative'] ?? null) === true
                    || ($s['read_only'] ?? null) === false
                    || in_array(strtoupper((string) ($s['risk_level'] ?? '')), ['HIGH', 'CRITICAL', 'MEDIUM'], true);
                if ($isMut) {
                    $mutativeCount++;
                }
            }
        }
        $totalStepsCount = count($plan['steps'] ?? []);
        $warningMessage = $plan['warning_message'] ?? (
            $totalStepsCount > 0
                ? "AVISO DE GOBERNANZA HITL: Con una única aprobación se autorizan todas las acciones de este plan ({$totalStepsCount} etapa(s) en total, de las cuales {$mutativeCount} son ETAPAS MUTATIVAS marcadas en rojo). Revisa el plan completo antes de aprobar."
                : 'AVISO DE GOBERNANZA HITL: Al aprobar esta solicitud se autorizan todas las acciones mutativas asociadas al plan.'
        );
        $plan['mutative_steps_count'] = $mutativeCount;
        $plan['total_steps_count'] = $totalStepsCount;
        $plan['warning_message'] = $warningMessage;

        $now = now();

        return [
            'id' => $approval->id,
            'thread_id' => $approval->thread_id,
            'task_id' => $approval->task_id,
            'agent_id' => $approval->agent_id,
            'agent_name' => $approval->agent?->name ?? 'Agente Desconocido',
            'status' => $approval->status,
            'risk_level' => $approval->risk_level,
            'action_summary' => $this->extractActionSummary($approval),
            'prompt' => $prompt,
            'tools_involved' => is_array($tools) ? array_values($tools) : [],
            'proposed_plan' => $plan,
            'mutative_steps_count' => $mutativeCount,
            'total_steps_count' => $totalStepsCount,
            'warning_message' => $warningMessage,
            'cognitive_steps' => is_array($cognitiveSteps) ? array_values($cognitiveSteps) : [],
            'compensation_commands' => $approval->compensation_commands_json ?? [],
            'created_at' => $approval->created_at?->toIso8601String(),
            'elapsed_time' => $approval->created_at?->diffForHumans(),
            'elapsed_seconds' => $approval->created_at ? max(0, (int) $approval->created_at->diffInSeconds($now)) : 0,
        ];
    }
}

