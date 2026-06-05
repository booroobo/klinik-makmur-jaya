<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_active_token_with_configured_expiration(): void
    {
        config(['sanctum.expiration' => 120]);
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $token = $user->tokens()->firstOrFail();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->greaterThan(now()->addMinutes(119)));
    }

    public function test_token_is_valid_before_timeout(): void
    {
        config(['sanctum.expiration' => 120]);
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $plainToken = $this->loginAndToken($user);

        $this->travel(119)->minutes();

        $this->withToken($plainToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_expired_token_is_rejected_and_session_timeout_is_audited(): void
    {
        config(['sanctum.expiration' => 1]);
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $plainToken = $this->loginAndToken($user);

        $this->travel(2)->minutes();

        $this->withToken($plainToken)
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Session expired. Please login again.')
            ->assertJsonPath('code', 'session_expired');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'role' => User::ROLE_ADMIN,
            'module' => 'auth',
            'action' => 'session_timeout',
            'status' => 'success',
            'description' => 'User session expired after 1 minutes',
        ]);
    }

    public function test_public_endpoint_remains_accessible_without_token(): void
    {
        $this->getJson('/api/catalog/medicines')
            ->assertOk();
    }

    public function test_protected_endpoint_rejects_expired_token(): void
    {
        config(['sanctum.expiration' => 1]);
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $plainToken = $this->loginAndToken($user);

        $this->travel(2)->minutes();

        $this->withToken($plainToken)
            ->getJson('/api/cart')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Session expired. Please login again.');
    }

    private function loginAndToken(User $user): string
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test',
        ])->assertOk()->json('data.token');
    }
}
