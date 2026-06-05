<?php

namespace App\Services;

use App\Mail\VerifyCustomerEmail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailVerificationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function send(Request $request, User $user): void
    {
        $plainToken = Str::random(64);
        $expirationMinutes = (int) config('app.email_verification_expiration', 1440);

        DB::transaction(function () use ($user, $plainToken, $expirationMinutes): void {
            $user->emailVerificationTokens()->delete();
            $user->emailVerificationTokens()->create([
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes($expirationMinutes),
            ]);
        });

        $verificationUrl = rtrim((string) config('app.url'), '/').'/api/verify-email/'.$plainToken;
        Mail::to($user->email)->send(new VerifyCustomerEmail(
            $user,
            $verificationUrl,
            (int) ceil($expirationMinutes / 60),
        ));

        $this->auditLogger->success($request, 'verification_email_sent', 'auth', 'Email verifikasi pelanggan dikirim.', [
            'email' => $user->email,
            'expires_in_minutes' => $expirationMinutes,
        ], $user);
    }

    public function verify(Request $request, string $plainToken): bool
    {
        $verificationToken = EmailVerificationToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $verificationToken || $verificationToken->expires_at->isPast()) {
            return false;
        }

        $user = $verificationToken->user;

        DB::transaction(function () use ($user): void {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
            $user->emailVerificationTokens()->delete();
        });

        $this->auditLogger->success($request, 'verify_email', 'auth', 'Email pelanggan berhasil diverifikasi.', [
            'email' => $user->email,
        ], $user);

        return true;
    }
}
