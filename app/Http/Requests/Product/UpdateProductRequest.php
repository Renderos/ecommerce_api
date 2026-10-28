<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateProductRequest',
    description: 'Todos los campos son opcionales; solo se actualizan los enviados.',
    properties: [
        new OA\Property(property: 'sku', type: 'string', maxLength: 50, example: 'ELEC-010'),
        new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Parlante Portátil v2'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0.5, example: 54.90),
        new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 40),
        new OA\Property(property: 'category', type: 'string', nullable: true),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'sku' => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0.5', 'max:999999.99', 'decimal:0,2'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
