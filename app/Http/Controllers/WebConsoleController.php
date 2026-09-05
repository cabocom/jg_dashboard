<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ApprovalRequest;
use Illuminate\Contracts\View\View;
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
}
