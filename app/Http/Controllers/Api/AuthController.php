<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AuthResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Inicio de sesión exitoso.'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
            new OA\Property(property: 'token', type: 'string', example: '1|Xz8k2...'),
            new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        ], type: 'object'),
    ]
)]
class AuthController extends ApiController
{
    #[OA\Post(
        path: '/api/auth/register',
        operationId: 'authRegister',
        summary: 'Registrar un nuevo cliente',
        description: 'Crea una cuenta con rol "customer" y devuelve un token de acceso. Limitado a 5 intentos por minuto.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Cliente registrado', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        // refresh() carga los valores por defecto de la BD (ej. role = customer)
        $user = User::create($request->validated())->refresh();

        return $this->success(
            $this->tokenPayload($user, $request->userAgent() ?: 'api'),
            'Cliente registrado correctamente.',
            201
        );
    }

    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'authLogin',
        summary: 'Iniciar sesión',
        description: 'Valida credenciales y emite un token Bearer de Sanctum. Limitado a 5 intentos por minuto por email/IP.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 422, description: 'Credenciales inválidas', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse', example: ['success' => false, 'message' => 'Los datos enviados no son válidos.', 'error_code' => 'VALIDATION_ERROR', 'errors' => ['email' => ['Las credenciales proporcionadas son incorrectas.']]])),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Mismo mensaje para email inexistente y contraseña errónea (evita enumeración de usuarios)
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        return $this->success(
            $this->tokenPayload($user, $request->validated('device_name') ?: ($request->userAgent() ?: 'api')),
            'Inicio de sesión exitoso.'
        );
    }

    #[OA\Get(
        path: '/api/auth/me',
        operationId: 'authMe',
        summary: 'Perfil del usuario autenticado',
        security: [['sanctum' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 200, description: 'Datos del usuario', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'OK'),
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'authLogout',
        summary: 'Cerrar sesión (revoca el token actual)',
        security: [['sanctum' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Sesión cerrada correctamente.'),
                new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Sesión cerrada correctamente.');
    }

    #[OA\Post(
        path: '/api/auth/logout-all',
        operationId: 'authLogoutAll',
        summary: 'Cerrar sesión en todos los dispositivos (revoca todos los tokens)',
        security: [['sanctum' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 200, description: 'Todos los tokens revocados'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'Se cerró la sesión en todos los dispositivos.');
    }

    protected function tokenPayload(User $user, string $deviceName): array
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->addMinutes((int) $minutes) : null;

        $token = $user->createToken(substr($deviceName, 0, 100), ['*'], $expiresAt);

        return [
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }
}
