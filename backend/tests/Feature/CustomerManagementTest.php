<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_customer_saves_complete_profile_fields(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Pelanggan Lengkap',
            'email' => 'lengkap@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Klinik No. 1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.user.role', User::ROLE_PELANGGAN)
            ->assertJsonPath('data.user.phone', '081234567890')
            ->assertJsonPath('data.user.address', 'Jl. Klinik No. 1');

        $this->assertDatabaseHas('users', [
            'email' => 'lengkap@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Klinik No. 1',
            'role' => User::ROLE_PELANGGAN,
        ]);
    }

    public function test_register_validates_unique_email_password_phone_and_address(): void
    {
        User::factory()->create(['email' => 'duplikat@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Invalid',
            'email' => 'duplikat@example.com',
            'phone' => '123',
            'address' => '',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'address', 'password']);
    }

    public function test_admin_can_view_and_toggle_block_customers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create([
            'role' => User::ROLE_PELANGGAN,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        Sanctum::actingAs($admin);

        // List customers
        $this->getJson('/api/admin/customers?keyword=john')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->id);

        // View single customer
        $this->getJson("/api/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'john@example.com');

        // Toggle block (block)
        $this->patchJson("/api/admin/customers/{$customer->id}/toggle-block")
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'is_blocked' => true,
        ]);

        // Toggle block (unblock)
        $this->patchJson("/api/admin/customers/{$customer->id}/toggle-block")
            ->assertOk()
            ->assertJsonPath('data.is_blocked', false);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'is_blocked' => false,
        ]);
    }

    public function test_admin_cannot_create_update_or_delete_customers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);

        Sanctum::actingAs($admin);

        // Test POST is disabled (not found / method not allowed)
        $this->postJson('/api/admin/customers', [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'phone' => '081234567899',
            'address' => 'Jl. Test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(405);

        // Test PUT is disabled
        $this->putJson("/api/admin/customers/{$customer->id}", [
            'name' => 'Updated Name',
        ])->assertStatus(405);

        // Test DELETE is disabled
        $this->deleteJson("/api/admin/customers/{$customer->id}")
            ->assertStatus(405);
    }

    public function test_blocked_customer_cannot_login(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_PELANGGAN,
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'is_blocked' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'blocked@example.com',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_blocked_customer_requests_are_rejected_and_token_revoked(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_PELANGGAN,
            'email' => 'test-blocked@example.com',
            'is_blocked' => false,
        ]);

        // Issue token
        Sanctum::actingAs($customer);

        // Request before block should be OK
        $this->getJson('/api/me')->assertOk();

        // Block the user in database directly
        $customer->is_blocked = true;
        $customer->save();

        // Request after block should return 403 Forbidden and revoke token
        $this->getJson('/api/me')->assertStatus(403);

        $this->assertEmpty($customer->tokens);
    }

    public function test_non_admin_cannot_access_customer_actions(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_PELANGGAN]);

        foreach ([User::ROLE_APOTEKER, User::ROLE_KASIR, User::ROLE_PELANGGAN] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/admin/customers')->assertForbidden();
            $this->patchJson("/api/admin/customers/{$customer->id}/toggle-block")->assertForbidden();
        }
    }

    public function test_customer_pagination_works(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->count(12)->create(['role' => User::ROLE_PELANGGAN]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/customers?per_page=5')
            ->assertOk()
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('last_page', 3);
    }
}
