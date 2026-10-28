<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'OK'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
    ]
)]
class OrderController extends ApiController
{
    public function __construct(
        protected OrderService $orders,
        protected PaymentService $payments,
    ) {}

    #[OA\Get(
        path: '/api/orders',
        operationId: 'ordersIndex',
        summary: 'Historial de compras del usuario autenticado',
        security: [['sanctum' => []]],
        tags: ['Órdenes'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'paid', 'failed', 'cancelled'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50, default: 10)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Órdenes del usuario, más recientes primero', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'OK'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,paid,failed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $request->user()->orders()
            ->with(['items', 'latestPayment'])
            ->withCount('items')
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return $this->paginated(OrderResource::collection($paginator), $paginator);
    }

    #[OA\Post(
        path: '/api/orders',
        operationId: 'ordersStore',
        summary: 'Crear una orden de compra',
        description: 'Los precios se toman del catálogo (no del cliente). Se valida y reserva el stock. La orden queda en estado "pending" hasta que se pague.',
        security: [['sanctum' => []]],
        tags: ['Órdenes'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreOrderRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Orden creada', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, description: 'Error de validación o stock insuficiente', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create(
            $request->user(),
            $request->validated('items'),
            $request->validated('shipping_address'),
            $request->validated('notes'),
        );

        return $this->success(new OrderResource($order->loadCount('items')), 'Orden creada correctamente.', 201);
    }

    #[OA\Get(
        path: '/api/orders/{id}',
        operationId: 'ordersShow',
        summary: 'Detalle de una orden',
        security: [['sanctum' => []]],
        tags: ['Órdenes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Orden', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return $this->success(new OrderResource($order->load(['items', 'latestPayment'])->loadCount('items')));
    }

    #[OA\Post(
        path: '/api/orders/{id}/cancel',
        operationId: 'ordersCancel',
        summary: 'Cancelar una orden no pagada',
        description: 'Devuelve el stock reservado y cancela en Stripe los PaymentIntent abiertos.',
        security: [['sanctum' => []]],
        tags: ['Órdenes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Orden cancelada', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/BusinessError'),
        ]
    )]
    public function cancel(Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        $order = $this->orders->cancel($order);
        $this->payments->cancelOpenIntents($order);

        return $this->success(new OrderResource($order->load('latestPayment')->loadCount('items')), 'Orden cancelada correctamente.');
    }
}
