<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $keyword = $filters['keyword'] ?? $filters['search'] ?? null;

        $customers = User::query()
            ->where('role', User::ROLE_PELANGGAN)
            ->withCount('orders')
            ->when($keyword, function ($query) use ($keyword): void {
                $value = '%'.$keyword.'%';
                $query->where(function ($search) use ($value): void {
                    $search->whereLike('name', $value, caseSensitive: false)
                        ->orWhereLike('email', $value, caseSensitive: false)
                        ->orWhereLike('phone', $value, caseSensitive: false)
                        ->orWhereLike('address', $value, caseSensitive: false);
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return response()->json($customers);
    }

    public function show(User $user): JsonResponse
    {
        $this->ensureCustomer($user);

        return response()->json([
            'data' => $user->loadCount('orders'),
        ]);
    }

    public function toggleBlock(Request $request, User $user): JsonResponse
    {
        $this->ensureCustomer($user);

        $user->is_blocked = !$user->is_blocked;
        $user->save();

        $action = $user->is_blocked ? 'blokir' : 'buka_blokir';
        $message = $user->is_blocked 
            ? "Pelanggan {$user->name} berhasil diblokir." 
            : "Blokir pelanggan {$user->name} berhasil dibuka.";

        // Revoke all tokens if user is blocked
        if ($user->is_blocked) {
            $user->tokens()->delete();
        }

        $this->auditLogger->success($request, $action, 'customer', $message, [
            'customer_id' => $user->id,
            'email' => $user->email,
            'is_blocked' => $user->is_blocked,
        ]);

        return response()->json([
            'message' => $message,
            'data' => $user->loadCount('orders'),
        ]);
    }

    private function ensureCustomer(User $user): void
    {
        abort_unless($user->role === User::ROLE_PELANGGAN, Response::HTTP_NOT_FOUND);
    }
}
