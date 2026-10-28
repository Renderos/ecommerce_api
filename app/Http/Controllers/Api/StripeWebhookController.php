<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StripeWebhookController extends ApiController
{
    #[OA\Post(
        path: '/api/stripe/webhook',
        operationId: 'stripeWebhook',
        summary: 'Webhook de Stripe',
        description: 'Endpoint que recibe eventos de Stripe (payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled, etc.). '
            .'La autenticidad se verifica con la cabecera `Stripe-Signature` y `STRIPE_WEBHOOK_SECRET`. No está pensado para ser invocado manualmente.',
        tags: ['Webhooks'],
        parameters: [new OA\Parameter(name: 'Stripe-Signature', in: 'header', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', description: 'Evento de Stripe')),
        responses: [
            new OA\Response(response: 200, description: 'Evento recibido', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Evento procesado.'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'payment_intent.succeeded'),
                    new OA\Property(property: 'handled', type: 'boolean', example: true),
                ], type: 'object'),
            ])),
            new OA\Response(response: 400, description: 'Firma inválida', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentService $payments): JsonResponse
    {
        $event = $gateway->constructWebhookEvent(
            $request->getContent(),
            (string) $request->header('Stripe-Signature')
        );

        $handled = $payments->handleWebhookEvent($event);

        return $this->success(
            ['type' => $event->type, 'handled' => $handled],
            $handled ? 'Evento procesado.' : 'Evento recibido sin acciones.'
        );
    }
}
