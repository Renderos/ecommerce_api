<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Order\PayOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class PaymentController extends ApiController
{
    public function __construct(protected PaymentService $payments) {}

    #[OA\Post(
        path: '/api/orders/{id}/pay',
        operationId: 'ordersPay',
        summary: 'Pagar una orden con Stripe',
        description: "Crea un PaymentIntent en Stripe por el total de la orden.\n\n"
            ."- **Con `payment_method`** (ej. `pm_card_visa` en modo test): el pago se confirma en el servidor y la orden pasa a `paid` si Stripe lo aprueba.\n"
            ."- **Sin `payment_method`**: se devuelve `client_secret` para confirmar el pago desde el frontend con Stripe.js (`stripe.confirmCardPayment`). El estado final llega vía webhook o consultando `GET /api/payments/{id}`.\n\n"
            .'Limitado a 10 solicitudes por minuto.',
        security: [['sanctum' => []]],
        tags: ['Pagos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID de la orden', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/PayOrderRequest')),
        responses: [
            new OA\Response(response: 200, description: 'PaymentIntent creado / pago procesado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Pago realizado con éxito.'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'payment', ref: '#/components/schemas/Payment'),
                    new OA\Property(property: 'order', ref: '#/components/schemas/Order'),
                    new OA\Property(property: 'client_secret', type: 'string', nullable: true, example: 'pi_3Q..._secret_...'),
                    new OA\Property(property: 'publishable_key', type: 'string', nullable: true, example: 'pk_test_...'),
                    new OA\Property(property: 'requires_action', type: 'boolean', example: false, description: 'true si el banco exige autenticación 3D Secure en el frontend'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 402, description: 'Tarjeta rechazada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', example: ['success' => false, 'message' => 'El pago fue rechazado: Your card was declined.', 'error_code' => 'CARD_DECLINED', 'errors' => ['decline_code' => ['generic_decline']]])),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'La orden no está en un estado pagable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 502, description: 'Error de comunicación con Stripe', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function pay(PayOrderRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        $result = $this->payments->pay($order, $request->validated('payment_method'));
        $payment = $result['payment'];

        $message = match (true) {
            $payment->status === Payment::STATUS_SUCCEEDED => 'Pago realizado con éxito.',
            $result['requires_action'] => 'El pago requiere autenticación adicional (3D Secure) en el frontend.',
            $payment->status === 'processing' => 'El pago se está procesando.',
            default => 'PaymentIntent creado. Confirma el pago con el client_secret.',
        };

        return $this->success([
            'payment' => new PaymentResource($payment),
            'order' => new OrderResource($order->refresh()->load('items')->loadCount('items')),
            'client_secret' => $result['client_secret'],
            'publishable_key' => config('services.stripe.key'),
            'requires_action' => $result['requires_action'],
        ], $message);
    }

    #[OA\Get(
        path: '/api/payments',
        operationId: 'paymentsIndex',
        summary: 'Historial de pagos del usuario autenticado',
        security: [['sanctum' => []]],
        tags: ['Pagos'],
        responses: [
            new OA\Response(response: 200, description: 'Pagos del usuario', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'OK'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $paginator = $request->user()->payments()->latest()->paginate(15);

        return $this->paginated(PaymentResource::collection($paginator), $paginator);
    }

    #[OA\Get(
        path: '/api/payments/{id}',
        operationId: 'paymentsShow',
        summary: 'Consultar un pago (sincroniza el estado con Stripe)',
        description: 'Consulta el PaymentIntent en Stripe y actualiza el estado local del pago y de la orden. Útil tras confirmar el pago en el frontend.',
        security: [['sanctum' => []]],
        tags: ['Pagos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Pago', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'OK'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 502, description: 'Error de comunicación con Stripe', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Payment $payment): JsonResponse
    {
        Gate::authorize('view', $payment);

        if (! in_array($payment->status, [Payment::STATUS_SUCCEEDED, Payment::STATUS_CANCELED, Payment::STATUS_FAILED], true)) {
            $payment = $this->payments->refresh($payment);
        }

        return $this->success(new PaymentResource($payment));
    }
}
