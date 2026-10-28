<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreProductRequest',
    required: ['sku', 'name', 'price', 'stock'],
    properties: [
        new OA\Property(property: 'sku', type: 'string', maxLength: 50, example: 'ELEC-010'),
        new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Parlante Portátil'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Parlante resistente al agua con 12h de batería.'),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0.5, example: 59.90),
        new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 25),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: 'Electrónica'),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true, example: 'https://placehold.co/600x400'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización por rol se aplica con el middleware "admin" en la ruta
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:products,sku'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Stripe exige un monto mínimo de 0.50 USD
            'price' => ['required', 'numeric', 'min:0.5', 'max:999999.99', 'decimal:0,2'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'category' => ['nullable', 'string', 'max:100'],
            'image_url' => ['nullable', 'url:http,https', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
