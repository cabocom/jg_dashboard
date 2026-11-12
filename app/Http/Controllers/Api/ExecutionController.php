<?php

declare(strict_types=1);

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Http\Controllers\Api;

use App\Actions\Execution\FetchExecutionTraceAction;
use App\Actions\Execution\TriggerAgentExecutionAction;
use App\DTO\Execution\TriggerExecutionInput;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionController extends Controller
{
    /**
     * Trigger or resume a cognitive agent execution via jg_agente.
     */
    public function trigger(Request $request, TriggerAgentExecutionAction $action): JsonResponse
    {
        $input = TriggerExecutionInput::fromRequest($request);
        $result = $action->execute($input, $request->user());

        return $result->toResponse();
    }

    /**
     * Inspect execution state, step history and HITL checkpoints for a specific thread on a target agent.
     */
    public function show(Request $request, string $agentId, string $threadId, FetchExecutionTraceAction $action): JsonResponse
    {
        return $action->execute($agentId, $threadId);
    }

    /**
     * Handle asynchronous execution status-update webhook (DASH-CODE-02 proxy/entrypoint).
     */
    public function statusUpdate(Request $request, string $executionId): JsonResponse
    {
        return app(InternalExecutionController::class)->statusUpdate($request, $executionId);
    }
}
