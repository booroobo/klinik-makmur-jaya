<?php

namespace Tests\Feature;

use App\Mail\VerifyCustomerEmail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_registration_sends_verification_email_and_does_not_login_user(): void
    {
        Mail::fake();

        $this->postJson('/api/register', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.verification_required', true)
            ->assertJsonMissingPath('data.token');

        $user = User::where('email', 'customer@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('email_verification_tokens', ['user_id' => $user->id]);
        Mail::assertSent(VerifyCustomerEmail::class, fn (VerifyCustomerEmail $mail) => $mail->hasTo($user->email));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'module' => 'auth', 'action' => 'verification_email_sent']);
    }

    public function test_invalid_registration_is_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/register', [
            'name' => 'A',
            'email' => 'invalid',
            'phone' => '0812-abc',
            'address' => '',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'address', 'password']);

        Mail::assertNothingSent();
    }

    public function test_customer_cannot_login_before_email_verification(): void
    {
        $user = User::factory()->unverified()->create(['role' => User::ROLE_PELANGGAN]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('code', 'email_not_verified');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => 'login',
            'status' => 'failed',
            'failure_reason' => 'Email not verified',
        ]);
    }

    public function test_valid_token_verifies_email_and_allows_login(): void
    {
        Mail::fake();
        $this->postJson('/api/register', $this->validPayload())->assertCreated();

        $verificationUrl = '';
        Mail::assertSent(VerifyCustomerEmail::class, function (VerifyCustomerEmail $mail) use (&$verificationUrl): bool {
            $verificationUrl = $mail->verificationUrl;
            return true;
        });

        $this->getJson(parse_url($verificationUrl, PHP_URL_PATH))
            ->assertOk()
            ->assertJsonPath('message', 'Email berhasil diverifikasi. Silakan login.');

        $user = User::where('email', 'customer@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('email_verification_tokens', ['user_id' => $user->id]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'module' => 'auth', 'action' => 'verify_email']);
    }

    public function test_expired_token_is_rejected_and_login_remains_blocked(): void
    {
        $user = User::factory()->unverified()->create(['role' => User::ROLE_PELANGGAN]);
        $plainToken = 'expired-verification-token';
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/verify-email/'.$plainToken)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'verification_token_invalid');
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden();
    }

    public function test_resend_replaces_token_and_sends_new_email(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create(['role' => User::ROLE_PELANGGAN]);
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'old-token'),
            'expires_at' => now()->addHour(),
        ]);

        $this->postJson('/api/resend-verification-email', ['email' => $user->email])->assertOk();

        $this->assertSame(1, $user->emailVerificationTokens()->count());
        $this->assertDatabaseMissing('email_verification_tokens', ['token_hash' => hash('sha256', 'old-token')]);
        Mail::assertSent(VerifyCustomerEmail::class, fn (VerifyCustomerEmail $mail) => $mail->hasTo($user->email));
    }

    private function validPayload(): array
    {
        return [
            'name' => 'Customer Verified',
            'email' => 'customer@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Klinik No. 1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }
}
