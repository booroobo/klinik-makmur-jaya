<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'role' => ['nullable', Rule::in(User::ROLES)],
            'status' => ['nullable', Rule::in(['success', 'failed'])],
            'actor_email' => ['nullable', 'string', 'max:255'],
            'http_status' => ['nullable', 'integer', 'min:100', 'max:599'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when(isset($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['role']), fn ($query) => $query->where('role', $filters['role']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['actor_email']), fn ($query) => $query->whereLike('actor_email', '%'.$filters['actor_email'].'%', caseSensitive: false))
            ->when(isset($filters['http_status']), fn ($query) => $query->where('http_status', $filters['http_status']))
            ->when(isset($filters['module']), fn ($query) => $query->where('module', $filters['module']))
            ->when(isset($filters['action']), fn ($query) => $query->where('action', $filters['action']))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['search']), function ($query) use ($filters): void {
                $keyword = '%'.$filters['search'].'%';

                $query->where(function ($searchQuery) use ($keyword): void {
                    $searchQuery
                        ->whereLike('description', $keyword, caseSensitive: false)
                        ->orWhereLike('failure_reason', $keyword, caseSensitive: false)
                        ->orWhereLike('action', $keyword, caseSensitive: false)
                        ->orWhereLike('module', $keyword, caseSensitive: false);
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json($logs);
    }
}
