<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Definición global de la especificación OpenAPI: info, servidor, seguridad,
 * tags y esquemas/respuestas reutilizables.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'API E-commerce Segura',
    description: "API RESTful de e-commerce construida con Laravel 12.\n\n"
        ."**Flujo completo:** registro → login (token Bearer) → catálogo → crear orden → pagar con Stripe → historial.\n\n"
        ."**Autenticación:** Laravel Sanctum. Obtén un token en `POST /api/auth/login` y presiona **Authorize** con el valor del token (sin el prefijo `Bearer`).\n\n"
        .'**Errores:** todas las respuestas de error usan el esquema `ErrorResponse`.',
    contact: new OA\Contact(name: 'Equipo Backend', email: 'backend@ecommerce.test'),
    license: new OA\License(name: 'MIT', identifier: 'MIT')
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Servidor de la API')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Token personal de Laravel Sanctum obtenido en /api/auth/login o /api/auth/register.'
)]
#[OA\Tag(name: 'Autenticación', description: 'Registro, login y gestión de sesión del cliente')]
#[OA\Tag(name: 'Productos', description: 'Catálogo público y administración de productos')]
#[OA\Tag(name: 'Órdenes', description: 'Creación de órdenes e historial de compras')]
#[OA\Tag(name: 'Pagos', description: 'Procesamiento de pagos con Stripe')]
#[OA\Tag(name: 'Webhooks', description: 'Eventos enviados por Stripe')]
#[OA\Schema(
    schema: 'ErrorResponse',
    required: ['success', 'message', 'error_code'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Descripción del error.'),
        new OA\Property(property: 'error_code', type: 'string', example: 'NOT_FOUND'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))
        ),
    ]
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Los datos enviados no son válidos.'),
        new OA\Property(property: 'error_code', type: 'string', example: 'VALIDATION_ERROR'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['email' => ['El campo email es obligatorio.']],
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))
        ),
    ]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 10),
    ]
)]
#[OA\Response(
    response: 'Unauthenticated',
    description: 'No autenticado (token ausente, inválido o expirado)',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', example: ['success' => false, 'message' => 'No autenticado. Envía un token Bearer válido.', 'error_code' => 'UNAUTHENTICATED'])
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Sin permisos para la acción',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', example: ['success' => false, 'message' => 'No tienes permisos para realizar esta acción.', 'error_code' => 'FORBIDDEN'])
)]
#[OA\Response(
    response: 'NotFound',
    description: 'Recurso no encontrado',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', example: ['success' => false, 'message' => 'Producto no encontrado.', 'error_code' => 'NOT_FOUND'])
)]
#[OA\Response(
    response: 'ValidationError',
    description: 'Error de validación',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
)]
#[OA\Response(
    response: 'BusinessError',
    description: 'Violación de regla de negocio',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', example: ['success' => false, 'message' => 'Stock insuficiente para uno o más productos.', 'error_code' => 'BUSINESS_RULE_VIOLATION', 'errors' => ['items.0.quantity' => ['Solo quedan 3 unidades de Mouse Inalámbrico.']]])
)]
#[OA\Response(
    response: 'TooManyRequests',
    description: 'Límite de solicitudes excedido',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse', example: ['success' => false, 'message' => 'Demasiadas solicitudes. Intenta nuevamente más tarde.', 'error_code' => 'TOO_MANY_REQUESTS'])
)]
class OpenApiSpec {}
