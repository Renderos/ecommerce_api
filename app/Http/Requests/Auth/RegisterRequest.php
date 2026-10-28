<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['name', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Juana Pérez'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juana@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'Secreta123!', description: 'Mínimo 8 caracteres, con mayúsculas, minúsculas, números y símbolos.'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Secreta123!'),
        new OA\Property(property: 'phone', type: 'string', maxLength: 30, nullable: true, example: '+56 9 8765 4321'),
    ]
)]
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta registrada con este correo.',
            'phone.regex' => 'El teléfono solo puede contener números, espacios y los caracteres + ( ) -.',
        ];
    }
}
