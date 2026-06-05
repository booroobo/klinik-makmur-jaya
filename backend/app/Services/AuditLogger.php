<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'plain_text_token',
        'authorization',
        'api_key',
        'apikey',
        'secret',
        'client_secret',
        'env',
    ];

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function log(
        Request $request,
        string $action,
        string $module,
        ?string $description = null,
        ?array $metadata = null,
        ?User $user = null,
    ): AuditLog {
        return $this->success($request, $action, $module, $description, $metadata, $user);
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function success(
        Request $request,
        string $action,
        string $module,
        ?string $description = null,
        ?array $metadata = null,
        ?User $user = null,
        ?string $actorEmail = null,
        ?int $httpStatus = null,
    ): AuditLog {
        return $this->write(
            request: $request,
            status: 'success',
            action: $action,
            module: $module,
            description: $description,
            metadata: $metadata,
            user: $user,
            actorEmail: $actorEmail,
            httpStatus: $httpStatus,
        );
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function failed(
        Request $request,
        string $action,
        string $module,
        string $failureReason,
        ?string $description = null,
        ?array $metadata = null,
        ?User $user = null,
        ?string $actorEmail = null,
        ?int $httpStatus = null,
    ): AuditLog {
        return $this->write(
            request: $request,
            status: 'failed',
            action: $action,
            module: $module,
            description: $description,
            metadata: $metadata,
            user: $user,
            actorEmail: $actorEmail,
            httpStatus: $httpStatus,
            failureReason: $failureReason,
        );
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function write(
        Request $request,
        string $status,
        string $action,
        string $module,
        ?string $description = null,
        ?array $metadata = null,
        ?User $user = null,
        ?string $actorEmail = null,
        ?int $httpStatus = null,
        ?string $failureReason = null,
    ): AuditLog {
        $actor = $user ?? $request->user();
        $requestEmail = $request->filled('email') ? $request->string('email')->lower()->toString() : null;

        return AuditLog::create([
            'user_id' => $actor?->id,
            'role' => $actor?->role,
            'status' => $status,
            'actor_email' => $actorEmail ?? $actor?->email ?? $requestEmail,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'http_status' => $httpStatus,
            'failure_reason' => $failureReason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $this->sanitizeMetadata($metadata),
        ]);
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @return array<string, mixed>|null
     */
    private function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        return $this->sanitizeArray($metadata);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'file_uploaded' => true,
                'original_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if (is_object($value)) {
            return method_exists($value, 'toArray') ? $this->sanitizeArray($value->toArray()) : '[object]';
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
