<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()
            ->with('actor:id,name,email')
            ->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', 'like', '%'.$request->string('entity_type')->toString().'%');
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(40)->withQueryString(),
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('actor:id,name,email');

        return view('admin.audit-logs.show', [
            'auditLog' => $auditLog,
        ]);
    }
}
