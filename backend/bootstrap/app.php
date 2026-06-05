<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\EnsureUserNotBlocked;
use App\Services\AuditLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'not_blocked' => EnsureUserNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $auditModuleActionForValidationFailure = function (Request $request): array {
            $method = $request->method();
            $path = $request->path();

            return match (true) {
                $method === 'POST' && $path === 'api/register' => ['auth', 'register'],
                $method === 'POST' && $path === 'api/checkout' => ['order', 'checkout'],
                $method === 'POST' && $path === 'api/medicines' => ['medicine', 'create'],
                in_array($method, ['POST', 'PUT'], true) && preg_match('#^api/medicines/\d+$#', $path) === 1 => ['medicine', 'update'],
                $method === 'POST' && $path === 'api/categories' => ['category', 'create'],
                $method === 'PUT' && preg_match('#^api/categories/\d+$#', $path) === 1 => ['category', 'update'],
                $method === 'POST' && $path === 'api/suppliers' => ['supplier', 'create'],
                $method === 'PUT' && preg_match('#^api/suppliers/\d+$#', $path) === 1 => ['supplier', 'update'],
                $method === 'POST' && $path === 'api/medicine-batches' => ['medicine_batch', 'create'],
                $method === 'PUT' && preg_match('#^api/medicine-batches/\d+$#', $path) === 1 => ['medicine_batch', 'update'],
                $method === 'PATCH' && preg_match('#^api/admin/prescriptions/\d+/approve$#', $path) === 1 => ['prescription', 'approve'],
                $method === 'PATCH' && preg_match('#^api/admin/prescriptions/\d+/reject$#', $path) === 1 => ['prescription', 'reject'],
                default => ['unknown', 'unknown'],
            };
        };

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                app(AuditLogger::class)->failed(
                    $request,
                    'access',
                    'authentication',
                    'Unauthenticated',
                    'Akses endpoint terproteksi tanpa login.',
                    [
                        'path' => $request->path(),
                        'method' => $request->method(),
                    ],
                    httpStatus: Response::HTTP_UNAUTHORIZED,
                );

                return response()->json([
                    'message' => 'Unauthenticated.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($auditModuleActionForValidationFailure) {
            [$module, $action] = $auditModuleActionForValidationFailure($request);

            if ($request->is('api/*') && [$module, $action] !== ['unknown', 'unknown']) {

                app(AuditLogger::class)->failed(
                    $request,
                    $action,
                    $module,
                    'Validation failed',
                    'Validasi request gagal.',
                    [
                        'path' => $request->path(),
                        'method' => $request->method(),
                        'errors' => $exception->errors(),
                        'input' => $request->except(['password', 'password_confirmation']),
                    ],
                    httpStatus: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            return null;
        });
    })->create();
