<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly EmailVerificationService $emailVerification,
    ) {}

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

        $this->emailVerification->send($request, $user);

        return response()->json([
            'message' => 'Registrasi berhasil. Email verifikasi telah dikirim.',
            'data' => [
                'user' => $user,
                'verification_required' => true,
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

        if ($user->role === User::ROLE_PELANGGAN && ! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();

            $this->auditLogger->failed(
                $request,
                'login',
                'auth',
                'Email not verified',
                'Login pelanggan ditolak karena email belum diverifikasi.',
                ['email' => $user->email],
                $user,
                httpStatus: Response::HTTP_FORBIDDEN,
            );

            return response()->json([
                'message' => 'Email belum diverifikasi. Silakan periksa inbox atau kirim ulang email verifikasi.',
                'code' => 'email_not_verified',
            ], Response::HTTP_FORBIDDEN);
        }

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
                'token' => $this->createSessionToken($user, $request->string('device_name', 'spa')->toString())->plainTextToken,
            ],
        ]);
    }

    public function verifyEmail(Request $request, string $token)
    {
        $verified = $this->emailVerification->verify($request, $token);

        if (! $verified) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Token verifikasi tidak valid atau sudah kedaluwarsa.',
                    'code' => 'verification_token_invalid',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/login?verification=expired');
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Email berhasil diverifikasi. Silakan login.']);
        }

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/login?verification=success');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc']]);
        $user = User::where('email', strtolower($data['email']))
            ->where('role', User::ROLE_PELANGGAN)
            ->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $this->emailVerification->send($request, $user);
        }

        return response()->json([
            'message' => 'Jika akun belum diverifikasi, email verifikasi baru telah dikirim.',
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

    private function createSessionToken(User $user, string $deviceName): NewAccessToken
    {
        $expiration = config('sanctum.expiration');
        $expiresAt = is_numeric($expiration) && (int) $expiration > 0
            ? now()->addMinutes((int) $expiration)
            : null;

        return $user->createToken($deviceName, ['*'], $expiresAt);
    }
}
