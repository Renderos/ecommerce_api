<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'OK'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Product'),
    ]
)]
class ProductController extends ApiController
{
    #[OA\Get(
        path: '/api/products',
        operationId: 'productsIndex',
        summary: 'Listado público de productos',
        description: 'Devuelve los productos activos, paginados, con filtros de búsqueda, categoría, precio y stock.',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Busca en nombre, descripción y SKU', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Electrónica')),
            new OA\Parameter(name: 'min_price', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'max_price', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'in_stock', in: 'query', required: false, description: 'Solo productos con stock disponible', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Prefijo "-" para orden descendente', schema: new OA\Schema(type: 'string', enum: ['name', '-name', 'price', '-price', 'created_at', '-created_at'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'OK'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function index(IndexProductRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = Product::query()->active()
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when(isset($filters['min_price']), fn ($q) => $q->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($q) => $q->where('price', '<=', $filters['max_price']))
            ->when($request->boolean('in_stock'), fn ($q) => $q->where('stock', '>', 0));

        $sort = $filters['sort'] ?? 'name';
        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');

        $paginator = $query->paginate($filters['per_page'] ?? 15)->withQueryString();

        return $this->paginated(ProductResource::collection($paginator), $paginator);
    }

    #[OA\Get(
        path: '/api/products/{id}',
        operationId: 'productsShow',
        summary: 'Detalle de un producto',
        tags: ['Productos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Producto encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(Product $product): JsonResponse
    {
        // Los productos inactivos no son visibles en el catálogo público
        abort_unless($product->is_active || request()->user('sanctum')?->isAdmin(), 404);

        return $this->success(new ProductResource($product));
    }

    #[OA\Post(
        path: '/api/products',
        operationId: 'productsStore',
        summary: 'Crear producto (solo admin)',
        security: [['sanctum' => []]],
        tags: ['Productos'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreProductRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Producto creado', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return $this->success(new ProductResource($product), 'Producto creado correctamente.', 201);
    }

    #[OA\Put(
        path: '/api/products/{id}',
        operationId: 'productsUpdate',
        summary: 'Actualizar producto (solo admin)',
        description: 'Acepta PUT o PATCH. Solo se modifican los campos enviados.',
        security: [['sanctum' => []]],
        tags: ['Productos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateProductRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return $this->success(new ProductResource($product->refresh()), 'Producto actualizado correctamente.');
    }

    #[OA\Delete(
        path: '/api/products/{id}',
        operationId: 'productsDestroy',
        summary: 'Eliminar producto (solo admin)',
        description: 'Eliminación lógica (soft delete): el producto deja de mostrarse pero se conserva para el historial de órdenes. No se permite si tiene órdenes pendientes de pago.',
        security: [['sanctum' => []]],
        tags: ['Productos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Producto eliminado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Producto eliminado correctamente.'),
                new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/BusinessError'),
        ]
    )]
    public function destroy(Product $product): JsonResponse
    {
        $hasPendingOrders = $product->orderItems()
            ->whereHas('order', fn ($q) => $q->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_FAILED]))
            ->exists();

        if ($hasPendingOrders) {
            throw new BusinessException('No se puede eliminar un producto con órdenes pendientes de pago.', 409);
        }

        $product->delete();

        return $this->success(null, 'Producto eliminado correctamente.');
    }
}
