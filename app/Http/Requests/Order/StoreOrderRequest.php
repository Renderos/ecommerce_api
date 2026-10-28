<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreOrderRequest',
    required: ['items'],
    properties: [
        new OA\Property(
            property: 'items',
            type: 'array',
            minItems: 1,
            items: new OA\Items(
                required: ['product_id', 'quantity'],
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 50, example: 2),
                ]
            )
        ),
        new OA\Property(property: 'shipping_address', type: 'string', maxLength: 255, nullable: true, example: 'Av. Siempre Viva 742, Santiago'),
        new OA\Property(property: 'notes', type: 'string', maxLength: 500, nullable: true, example: 'Entregar en conserjería'),
    ]
)]
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'La orden debe contener al menos un producto.',
            'items.*.product_id.exists' => 'El producto :input no existe.',
            'items.*.product_id.distinct' => 'No repitas productos; usa el campo quantity.',
        ];
    }
}
