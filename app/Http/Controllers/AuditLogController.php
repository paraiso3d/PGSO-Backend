<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function getAuditLogs(Request $request)
    {
        // Fetch audit logs with optional filters
        $logs = AuditLog::query();

        if ($request->has('user_id')) {
            $logs->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $logs->where('action', $request->action);
        }

        $logs->orderBy('created_at', 'desc');

        return response()->json([
            'success' => true,
            'data' => $logs->get(),
        ]);
    }
}
