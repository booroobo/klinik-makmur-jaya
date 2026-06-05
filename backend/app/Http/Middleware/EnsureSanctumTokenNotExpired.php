<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureSanctumTokenNotExpired
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $token = $this->accessTokenFromRequest($request);

        if ($token && $this->isExpired($token)) {
            $user = $token->tokenable instanceof User ? $token->tokenable : null;
            $minutes = (int) config('sanctum.expiration', 120);

            $this->auditLogger->success(
                $request,
                'session_timeout',
                'auth',
                "User session expired after {$minutes} minutes",
                [
                    'token_id' => $token->id,
                    'token_name' => $token->name,
                    'expired_at' => $token->expires_at?->toISOString(),
                    'path' => $request->path(),
                    'method' => $request->method(),
                ],
                $user,
                httpStatus: Response::HTTP_UNAUTHORIZED,
            );

            return response()->json([
                'message' => 'Session expired. Please login again.',
                'code' => 'session_expired',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    private function accessTokenFromRequest(Request $request): ?PersonalAccessToken
    {
        $bearerToken = $request->bearerToken();

        return $bearerToken ? PersonalAccessToken::findToken($bearerToken) : null;
    }

    private function isExpired(PersonalAccessToken $token): bool
    {
        if ($token->expires_at?->isPast()) {
            return true;
        }

        $expiration = config('sanctum.expiration');

        return is_numeric($expiration)
            && (int) $expiration > 0
            && $token->created_at
            && $token->created_at->lte(now()->subMinutes((int) $expiration));
    }
}
