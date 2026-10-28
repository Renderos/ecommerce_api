<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Convierte cualquier excepción lanzada en rutas /api/* en una respuesta JSON
 * con formato consistente:
 *
 * { "success": false, "message": "...", "error_code": "...", "errors": {...} }
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->respond(
                'Los datos enviados no son válidos.', 422, 'VALIDATION_ERROR', $e->errors()
            ),
            $e instanceof AuthenticationException => $this->respond(
                'No autenticado. Envía un token Bearer válido.', 401, 'UNAUTHENTICATED'
            ),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => $this->respond(
                'No tienes permisos para realizar esta acción.', 403, 'FORBIDDEN'
            ),
            $e instanceof ModelNotFoundException => $this->respond(
                $this->modelName($e).' no encontrado.', 404, 'NOT_FOUND'
            ),
            $e instanceof NotFoundHttpException => $this->respond(
                $e->getPrevious() instanceof ModelNotFoundException
                    ? $this->modelName($e->getPrevious()).' no encontrado.'
                    : 'Recurso o ruta no encontrada.',
                404, 'NOT_FOUND'
            ),
            $e instanceof MethodNotAllowedHttpException => $this->respond(
                'Método HTTP no permitido para esta ruta.', 405, 'METHOD_NOT_ALLOWED'
            ),
            $e instanceof ThrottleRequestsException => $this->respond(
                'Demasiadas solicitudes. Intenta nuevamente más tarde.', 429, 'TOO_MANY_REQUESTS'
            ),
            $e instanceof BusinessException => $this->respond(
                $e->getMessage(), $e->status(), 'BUSINESS_RULE_VIOLATION', $e->errors()
            ),
            $e instanceof SignatureVerificationException => $this->respond(
                'Firma del webhook de Stripe inválida.', 400, 'INVALID_SIGNATURE'
            ),
            $e instanceof CardException => $this->respond(
                'El pago fue rechazado: '.$e->getMessage(), 402, 'CARD_DECLINED',
                ['decline_code' => [$e->getDeclineCode() ?? $e->getStripeCode()]]
            ),
            // Stripe rechaza un dato enviado por el cliente (ej. un payment_method inexistente)
            $e instanceof InvalidRequestException && $e->getStripeParam() === 'payment_method' => $this->respond(
                'El método de pago indicado no existe o no es válido.', 422, 'VALIDATION_ERROR',
                ['payment_method' => [$e->getMessage()]]
            ),
            $e instanceof ApiErrorException => $this->respond(
                'Error al comunicarse con la pasarela de pago.', 502, 'PAYMENT_GATEWAY_ERROR',
                config('app.debug') ? ['stripe' => [$e->getMessage()]] : []
            ),
            $e instanceof HttpExceptionInterface => $this->respond(
                $e->getMessage() ?: 'Error en la solicitud.', $e->getStatusCode(), 'HTTP_ERROR'
            ),
            default => $this->respond(
                config('app.debug') ? $e->getMessage() : 'Error interno del servidor.',
                500, 'SERVER_ERROR'
            ),
        };
    }

    protected function respond(string $message, int $status, string $code, array $errors = []): JsonResponse
    {
        $body = [
            'success' => false,
            'message' => $message,
            'error_code' => $code,
        ];

        if (! empty($errors)) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    protected function modelName(ModelNotFoundException $e): string
    {
        $names = [
            'Product' => 'Producto',
            'Order' => 'Orden',
            'Payment' => 'Pago',
            'User' => 'Usuario',
        ];

        $base = class_basename($e->getModel());

        return $names[$base] ?? Str::headline($base);
    }
}
