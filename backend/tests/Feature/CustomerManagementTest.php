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

    public function test_admin_can_crud_customers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/admin/customers', [
            'name' => 'Customer Admin',
            'email' => 'customer-admin@example.com',
            'phone' => '081234567891',
            'address' => 'Jl. Admin Customer',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.role', User::ROLE_PELANGGAN);

        $customerId = $createResponse->json('data.id');

        $this->getJson('/api/admin/customers?keyword=customer-admin')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customerId);

        $this->getJson("/api/admin/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('data.email', 'customer-admin@example.com');

        $this->putJson("/api/admin/customers/{$customerId}", [
            'name' => 'Customer Updated',
            'email' => 'customer-updated@example.com',
            'phone' => '081234567892',
            'address' => 'Jl. Updated',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Customer Updated');

        $this->deleteJson("/api/admin/customers/{$customerId}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $customerId]);
    }

    public function test_non_admin_cannot_access_customer_crud(): void
    {
        foreach ([User::ROLE_APOTEKER, User::ROLE_KASIR, User::ROLE_PELANGGAN] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/admin/customers')->assertForbidden();
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
