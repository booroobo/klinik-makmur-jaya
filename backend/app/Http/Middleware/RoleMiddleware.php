<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            app(AuditLogger::class)->failed(
                $request,
                'access',
                'authorization',
                'Role not allowed',
                'Akses endpoint ditolak karena role tidak sesuai.',
                [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'required_roles' => $roles,
                ],
                $user,
                null,
                Response::HTTP_FORBIDDEN,
            );

            return response()->json([
                'message' => 'Anda tidak memiliki akses ke endpoint ini.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
