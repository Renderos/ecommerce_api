<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Payment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'order_id', type: 'integer', example: 1),
        new OA\Property(property: 'provider', type: 'string', example: 'stripe'),
        new OA\Property(property: 'stripe_payment_intent_id', type: 'string', example: 'pi_3Q...'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 190.16),
        new OA\Property(property: 'currency', type: 'string', example: 'usd'),
        new OA\Property(property: 'status', type: 'string', example: 'succeeded', description: 'Estado del PaymentIntent en Stripe.'),
        new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'pm_1Q...'),
        new OA\Property(property: 'failure_message', type: 'string', nullable: true),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'provider' => $this->provider,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'failure_message' => $this->failure_message,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
