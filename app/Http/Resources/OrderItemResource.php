<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'product_name', type: 'string', example: 'Audífonos Bluetooth Pro'),
        new OA\Property(property: 'unit_price', type: 'number', format: 'float', example: 79.90),
        new OA\Property(property: 'quantity', type: 'integer', example: 2),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 159.80),
    ]
)]
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'unit_price' => (float) $this->unit_price,
            'quantity' => $this->quantity,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
