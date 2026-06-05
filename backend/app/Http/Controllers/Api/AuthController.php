<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'phone' => $request->string('phone')->toString(),
            'address' => $request->string('address')->toString(),
            'password' => $request->string('password')->toString(),
            'role' => User::ROLE_PELANGGAN,
        ]);

        $this->auditLogger->log(
            $request,
            'register',
            'auth',
            "Pelanggan {$user->name} melakukan registrasi.",
            ['email' => $user->email],
            $user,
        );

        return response()->json([
            'message' => 'Registrasi pelanggan berhasil.',
            'data' => [
                'user' => $user,
                'token' => $user->createToken($request->string('device_name', 'spa')->toString())->plainTextToken,
            ],
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $email = $request->string('email')->lower()->toString();
        $user = User::where('email', $email)->first();

        if ($user && $user->is_blocked) {
            $this->auditLogger->failed(
                $request,
                'login',
                'auth',
                'Blocked account',
                "Percobaan login gagal karena akun {$email} diblokir.",
                ['email' => $email],
                $user,
                $email,
                Response::HTTP_FORBIDDEN,
            );

            return response()->json([
                'message' => 'Akun Anda telah diblokir. Silakan hubungi admin.',
                'errors' => [
                    'email' => ['Akun Anda telah diblokir. Silakan hubungi admin.'],
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        if (! Auth::attempt($credentials)) {
            $failureReason = $user ? 'Invalid credentials' : 'Email not found';

            $this->auditLogger->failed(
                $request,
                'login',
                'auth',
                $failureReason,
                "Percobaan login gagal untuk {$email}.",
                ['email' => $email],
                $user,
                $email,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );

            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var User $user */
        $user = Auth::user();

        $this->auditLogger->log(
            $request,
            'login',
            'auth',
            "{$user->name} berhasil login.",
            ['email' => $user->email],
            $user,
        );

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'user' => $user,
                'token' => $user->createToken($request->string('device_name', 'spa')->toString())->plainTextToken,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Data user saat ini.',
            'data' => [
                'user' => $request->user(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $this->auditLogger->log(
            $request,
            'logout',
            'auth',
            $user ? "{$user->name} melakukan logout." : 'Pengguna melakukan logout.',
            null,
            $user,
        );

        $request->user()?->currentAccessToken()?->delete();

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
