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

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['role'] = User::ROLE_PELANGGAN;

        $customer = User::create($data);

        $this->auditLogger->success($request, 'create', 'customer', "Pelanggan {$customer->name} dibuat admin.", [
            'customer_id' => $customer->id,
            'email' => $customer->email,
        ]);

        return response()->json([
            'message' => 'Pelanggan berhasil dibuat.',
            'data' => $customer,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureCustomer($user);
        $before = $user->only(['name', 'email', 'phone', 'address']);
        $data = $this->validated($request, $user);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data + ['role' => User::ROLE_PELANGGAN]);

        $this->auditLogger->success($request, 'update', 'customer', "Pelanggan {$user->name} diperbarui admin.", [
            'customer_id' => $user->id,
            'before' => $before,
            'after' => $user->only(['name', 'email', 'phone', 'address']),
        ]);

        return response()->json([
            'message' => 'Pelanggan berhasil diperbarui.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureCustomer($user);
        $user->delete();

        $this->auditLogger->success($request, 'delete', 'customer', "Pelanggan {$user->name} dihapus admin.", [
            'customer_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Pelanggan berhasil dihapus.',
        ]);
    }

    private function ensureCustomer(User $user): void
    {
        abort_unless($user->role === User::ROLE_PELANGGAN, Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $customer = null): array
    {
        $passwordRules = $customer
            ? ['nullable', 'string', 'min:8', 'confirmed']
            : ['required', 'string', 'min:8', 'confirmed'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($customer?->id)],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{10,50}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'password' => $passwordRules,
        ]);
    }
}
