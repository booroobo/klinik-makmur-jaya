<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_blocked) {
            // Revoke current token immediately
            $user->currentAccessToken()?->delete();

            app(AuditLogger::class)->failed(
                $request,
                'access',
                'authorization',
                'Blocked account request rejected',
                'Akses ditolak karena akun diblokir.',
                [
                    'path' => $request->path(),
                    'method' => $request->method(),
                ],
                $user,
                null,
                Response::HTTP_FORBIDDEN,
            );

            return response()->json([
                'message' => 'Akun Anda telah ditangguhkan/diblokir.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
