<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listing_shows_only_active_products_with_filters(): void
    {
        Product::factory()->create(['name' => 'Mouse Gamer', 'price' => 30, 'category' => 'Electrónica']);
        Product::factory()->create(['name' => 'Silla', 'price' => 150, 'category' => 'Hogar']);
        Product::factory()->inactive()->create(['name' => 'Oculto']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissing(['name' => 'Oculto']);

        $this->getJson('/api/products?search=mouse&max_price=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Mouse Gamer');

        $this->getJson('/api/products?sort=invalid')->assertStatus(422);
    }

    public function test_show_returns_404_json_for_missing_or_inactive_product(): void
    {
        $inactive = Product::factory()->inactive()->create();

        $this->getJson('/api/products/999')
            ->assertNotFound()
            ->assertJson(['success' => false, 'error_code' => 'NOT_FOUND', 'message' => 'Producto no encontrado.']);

        $this->getJson("/api/products/{$inactive->id}")->assertNotFound();
    }

    public function test_guest_and_customer_cannot_manage_products(): void
    {
        $payload = ['sku' => 'T-1', 'name' => 'Test', 'price' => 10, 'stock' => 1];

        $this->postJson('/api/products', $payload)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/products', $payload)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FORBIDDEN');
    }

    public function test_admin_can_crud_products(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/api/products', [
            'sku' => 'NEW-1', 'name' => 'Parlante Portátil', 'price' => 59.9, 'stock' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'parlante-portatil')
            ->json('data.id');

        $this->postJson('/api/products', ['sku' => 'NEW-1', 'name' => 'Otro', 'price' => 0.1, 'stock' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku', 'price', 'stock']);

        $this->patchJson("/api/products/{$id}", ['price' => 49.9])
            ->assertOk()
            ->assertJsonPath('data.price', 49.9);

        $this->deleteJson("/api/products/{$id}")->assertOk();
        $this->assertSoftDeleted('products', ['id' => $id]);
        $this->getJson("/api/products/{$id}")->assertNotFound();
    }
}
