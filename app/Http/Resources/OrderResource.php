<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Order',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-20261028-8KX2QZ'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'paid', 'failed', 'cancelled'], example: 'pending'),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 159.80),
        new OA\Property(property: 'tax', type: 'number', format: 'float', example: 30.36),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 190.16),
        new OA\Property(property: 'currency', type: 'string', example: 'usd'),
        new OA\Property(property: 'shipping_address', type: 'string', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'items_count', type: 'integer', example: 1),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItem')),
        new OA\Property(property: 'latest_payment', ref: '#/components/schemas/Payment', nullable: true),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'shipping_address' => $this->shipping_address,
            'notes' => $this->notes,
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'latest_payment' => new PaymentResource($this->whenLoaded('latestPayment')),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
