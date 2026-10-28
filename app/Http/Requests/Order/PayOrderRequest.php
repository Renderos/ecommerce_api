<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PayOrderRequest',
    properties: [
        new OA\Property(
            property: 'payment_method',
            type: 'string',
            nullable: true,
            example: 'pm_card_visa',
            description: "ID de PaymentMethod de Stripe. Si se envía, el pago se confirma en el servidor. En modo test puedes usar 'pm_card_visa' (aprobado), 'pm_card_chargeDeclined' (rechazado) o 'pm_card_authenticationRequired' (requiere 3DS). Si se omite, se devuelve el client_secret para confirmarlo desde el frontend con Stripe.js."
        ),
    ]
)]
class PayOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'string', 'max:255', 'regex:/^pm_[A-Za-z0-9_]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.regex' => 'El payment_method debe ser un ID válido de Stripe (pm_...).',
        ];
    }
}
