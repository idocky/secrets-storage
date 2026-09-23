<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'is_admin' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Введите логин',
            'login.unique' => 'Пользователь с таким логином уже существует',
            'password.required' => 'Введите пароль',
            'tags.*.max' => 'Тег не длиннее 50 символов',
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

    public function isAdmin(): bool
    {
        return $this->boolean('is_admin');
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return collect($this->validated('tags', []))
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->values()
            ->all();
    }
}
