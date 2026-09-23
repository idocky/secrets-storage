<?php

namespace App\Secrets\Strategies;

use App\Enums\SecretType;
use App\Http\Requests\StoreSecretRequest;
use App\Models\Secret;
use App\Secrets\Concerns\BuildsSecretTypeContract;
use App\Secrets\Contracts\SecretTypeStrategy;

final class PasswordStrategy implements SecretTypeStrategy
{
    use BuildsSecretTypeContract;

    public function type(): SecretType
    {
        return SecretType::Password;
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:4096'],
            'files' => ['prohibited'],
            'description' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Введите значение',
            'files.prohibited' => 'Для Password / API key нельзя прикрепить файлы',
            'description.prohibited' => 'Для Password / API key нельзя указать описание',
        ];
    }

    public function persist(Secret $secret, StoreSecretRequest $request): void
    {
        $secret->forceFill([
            'value' => $request->secretValue(),
        ])->save();
    }

    public function contract(): array
    {
        return $this->makeContract([
            [
                'name' => 'value',
                'required' => true,
                'widget' => 'password',
                'label' => 'Значение',
                'hint' => 'Пароль или API key',
            ],
        ]);
    }
}
