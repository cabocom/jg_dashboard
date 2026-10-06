<?php

namespace App\Http\Controllers\Api\Adapters;

use App\Adapters\HttpWebhookAdapter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookAdapterController extends Controller
{
    public function __construct(
        protected HttpWebhookAdapter $adapter
    ) {}

    /**
     * Handle generic external webhook stimulus and route it to an agent.
     */
    public function handle(Request $request, ?string $agent_id = null): JsonResponse
    {
        try {
            $result = $this->adapter->handle($request, $agent_id);

            return response()->json(
                $result['body'],
                $result['http_code'],
                $result['headers']
            );
        } catch (Throwable $e) {
            Log::error("[WebhookAdapterController] Error inesperado en webhook adapter: " . $e->getMessage(), [
                'exception' => $e,
                'agent_id' => $agent_id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Adapter Internal Error',
                'message' => 'Ocurrió un error al procesar el estímulo del webhook externo.',
            ], 500);
        }
    }
}
