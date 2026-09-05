<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Services\Agents\AgentRegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebConsoleController extends Controller
{
    /**
     * Render the high-tech interactive web console for operators and admins.
     */
    public function index(Request $request): View
    {
        $totalAgents = Agent::count();
        $activeAgents = Agent::where('status', 'ACTIVE')->count();
        $offlineAgents = Agent::where('status', 'OFFLINE')->count();
        $pendingApprovalsCount = ApprovalRequest::pending()->count();

        return view('console.index', [
            'totalAgents' => $totalAgents,
            'activeAgents' => $activeAgents,
            'offlineAgents' => $offlineAgents,
            'pendingApprovalsCount' => $pendingApprovalsCount,
        ]);
    }

    /**
     * Provision a new agent from the web console and return its one-time bootstrap token.
     */
    public function storeAgent(Request $request, AgentRegistrationService $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'version' => 'nullable|string|max:32',
            'ttl_hours' => 'nullable|integer|min:1|max:168',
        ]);

        $result = $service->createAgent(
            name: $validated['name'],
            version: $validated['version'] ?? '1.0.0',
            ttlHours: (int) ($validated['ttl_hours'] ?? 24),
            adminUser: $request->user()
        );

        return response()->json([
            'agent_id' => $result['agent_id'],
            'name' => $result['name'],
            'status' => $result['status'],
            'bootstrap_token' => $result['bootstrap_token'],
            'expires_at' => $result['expires_at'],
            'ttl_hours' => $result['ttl_hours'],
            'created_at' => $result['agent']->created_at?->toISOString(),
        ], 201);
    }
}

