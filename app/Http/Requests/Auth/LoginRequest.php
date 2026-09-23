<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Введите логин',
            'password.required' => 'Введите пароль',
        ];
    }

    public function login(): string
    {
        return (string) $this->validated('login');
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }

    public function remember(): bool
    {
        return $this->boolean('remember');
    }
}
