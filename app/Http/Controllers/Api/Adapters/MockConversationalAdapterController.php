<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Http\Controllers\Api\Adapters;

use App\Adapters\Conversational\MockAsteriskChatAdapter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MockConversationalAdapterController extends Controller
{
    public function __construct(
        protected MockAsteriskChatAdapter $adapter
    ) {}

    /**
     * Handle simulated conversational stimulus (Asterisk / Chat) without requiring a real PBX.
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
            Log::error("[MockConversationalAdapterController] Error inesperado en mock conversacional: " . $e->getMessage(), [
                'exception' => $e,
                'agent_id' => $agent_id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Conversational Adapter Error',
                'message' => 'Ocurrió un error al procesar el estímulo conversacional simulado.',
            ], 500);
        }
    }
}
