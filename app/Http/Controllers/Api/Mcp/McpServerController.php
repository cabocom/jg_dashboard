<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Http\Controllers\Api\Mcp;

use App\Actions\Mcp\CallMcpToolAction;
use App\Actions\Mcp\ListMcpToolsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class McpServerController extends Controller
{
    public function __construct(
        protected ListMcpToolsAction $listToolsAction,
        protected CallMcpToolAction $callToolAction
    ) {}

    /**
     * Punto de entrada Streamable HTTP para el servidor MCP sobre JSON-RPC 2.0.
     */
    public function handle(Request $request): Response
    {
        $antiRebindingError = $this->verifyAntiDnsRebinding($request);
        if ($antiRebindingError !== null) {
            return $antiRebindingError;
        }

        $sessionHeaders = [];
        if ($sessionId = $request->header('Mcp-Session-Id')) {
            $sessionHeaders['Mcp-Session-Id'] = $sessionId;
        }

        if ($request->input('jsonrpc') !== '2.0') {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: jsonrpc version must be exactly 2.0',
                ],
            ], Response::HTTP_BAD_REQUEST)->withHeaders($sessionHeaders);
        }

        $method = (string) $request->input('method', '');
        $id = $request->input('id');
        $params = $request->input('params', []);

        return $this->dispatchRpcMethod($request, $method, $id, $params, $sessionHeaders);
    }

    /**
     * Valida cabeceras Host y Origin para prevenir ataques DNS Rebinding.
     */
    protected function verifyAntiDnsRebinding(Request $request): ?JsonResponse
    {
        $origin = $request->header('Origin');
        if ($origin === null) {
            return null;
        }

        $appUrl = (string) config('app.url', 'http://localhost');
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $allowedHosts = config('services.mcp.allowed_origins', [$appHost, 'localhost', '127.0.0.1']);

        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        $originHostWithPort = $originPort ? "{$originHost}:{$originPort}" : $originHost;

        $isAllowed = in_array('*', $allowedHosts, true)
            || in_array($origin, $allowedHosts, true)
            || in_array($originHost, $allowedHosts, true)
            || in_array($originHostWithPort, $allowedHosts, true);

        if (! $isAllowed) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => [
                    'code' => -32001,
                    'message' => "Forbidden: Origin '{$origin}' rejected by Anti-DNS Rebinding policy",
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * Despacha el método JSON-RPC 2.0 solicitado.
     *
     * @param  mixed  $id
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $sessionHeaders
     */
    protected function dispatchRpcMethod(
        Request $request,
        string $method,
        mixed $id,
        array $params,
        array $sessionHeaders
    ): Response {
        return match ($method) {
            'initialize' => $this->handleInitialize($id, $sessionHeaders),
            'notifications/initialized' => response(status: 204)->withHeaders($sessionHeaders),
            'ping' => response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => (object) []])->withHeaders($sessionHeaders),
            'tools/list' => $this->handleToolsList($id, $sessionHeaders),
            'tools/call' => $this->handleToolsCall($request, $id, $params, $sessionHeaders),
            default => response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32601, 'message' => "Method '{$method}' not found"],
            ])->withHeaders($sessionHeaders),
        };
    }

    /**
     * Handshake 'initialize' de MCP.
     *
     * @param  array<string, string>  $sessionHeaders
     */
    protected function handleInitialize(mixed $id, array $sessionHeaders): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => '2024-11-05',
                'serverInfo' => [
                    'name' => 'jg_dashboard-mcp',
                    'version' => '1.0.0',
                ],
                'capabilities' => [
                    'tools' => (object) [],
                ],
            ],
        ])->withHeaders($sessionHeaders);
    }

    /**
     * Descubrimiento de herramientas 'tools/list'.
     *
     * @param  array<string, string>  $sessionHeaders
     */
    protected function handleToolsList(mixed $id, array $sessionHeaders): JsonResponse
    {
        $result = $this->listToolsAction->execute();

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ])->withHeaders($sessionHeaders);
    }

    /**
     * Ejecución de herramientas 'tools/call'.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $sessionHeaders
     */
    protected function handleToolsCall(Request $request, mixed $id, array $params, array $sessionHeaders): JsonResponse
    {
        $apiToken = $request->attributes->get('api_token');
        if ($apiToken && ! $apiToken->hasScope('mcp:execute')) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => "Unauthorized: Token lacks required 'mcp:execute' scope",
                ],
            ], Response::HTTP_FORBIDDEN)->withHeaders($sessionHeaders);
        }

        $toolName = (string) ($params['name'] ?? '');
        $arguments = (array) ($params['arguments'] ?? []);

        $callResult = $this->callToolAction->execute($toolName, $arguments, $apiToken);

        if (isset($callResult['code'])) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $callResult['code'],
                    'message' => $callResult['message'],
                ],
            ])->withHeaders($sessionHeaders);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $callResult,
        ])->withHeaders($sessionHeaders);
    }
}
