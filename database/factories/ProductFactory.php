<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(12),
            'price' => fake()->randomFloat(2, 5, 500),
            'stock' => fake()->numberBetween(5, 100),
            'category' => fake()->randomElement(['Electrónica', 'Hogar', 'Deportes', 'Ropa']),
            'image_url' => fake()->imageUrl(640, 480, 'products'),
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
