<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $actor = User::factory()->create(['role' => User::ROLE_PELANGGAN]);

        AuditLog::create([
            'user_id' => $actor->id,
            'role' => $actor->role,
            'status' => 'success',
            'action' => 'login',
            'module' => 'auth',
            'description' => 'Pelanggan login.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/audit-logs?search=login');

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'login')
            ->assertJsonPath('data.0.module', 'auth')
            ->assertJsonPath('data.0.user.id', $actor->id);
    }

    public function test_non_admin_cannot_view_audit_logs(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    public function test_login_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'pelanggan@example.com',
            'role' => User::ROLE_PELANGGAN,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ], [
            'User-Agent' => 'PHPUnit Agent',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'role' => User::ROLE_PELANGGAN,
            'status' => 'success',
            'action' => 'login',
            'module' => 'auth',
        ]);
    }

    public function test_failed_login_with_wrong_password_creates_failed_audit_log(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ], [
            'User-Agent' => 'PHPUnit Agent',
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'role' => User::ROLE_ADMIN,
            'status' => 'failed',
            'actor_email' => 'admin@example.com',
            'action' => 'login',
            'module' => 'auth',
            'http_status' => 422,
            'failure_reason' => 'Invalid credentials',
        ]);
    }

    public function test_failed_login_audit_log_does_not_store_password_in_metadata(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ])->assertUnprocessable();

        $log = AuditLog::where('status', 'failed')->where('action', 'login')->firstOrFail();

        $this->assertSame(['email' => 'admin@example.com'], $log->metadata);
        $this->assertArrayNotHasKey('password', $log->metadata ?? []);
        $this->assertStringNotContainsString('wrong-password', json_encode($log->metadata));
    }

    public function test_admin_can_filter_failed_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        AuditLog::create([
            'user_id' => $admin->id,
            'role' => User::ROLE_ADMIN,
            'status' => 'success',
            'action' => 'login',
            'module' => 'auth',
            'description' => 'Login berhasil.',
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'role' => User::ROLE_ADMIN,
            'status' => 'failed',
            'actor_email' => $admin->email,
            'action' => 'login',
            'module' => 'auth',
            'description' => 'Login gagal.',
            'failure_reason' => 'Invalid credentials',
            'http_status' => 422,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/audit-logs?status=failed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.failure_reason', 'Invalid credentials');
    }

    public function test_category_create_creates_audit_log(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/categories', [
            'name' => 'Obat Uji',
            'description' => 'Kategori untuk pengujian.',
        ]);

        $response->assertCreated();

        $category = Category::where('name', 'Obat Uji')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'role' => User::ROLE_ADMIN,
            'action' => 'create',
            'module' => 'category',
            'description' => "Kategori {$category->name} dibuat.",
        ]);
    }
}
