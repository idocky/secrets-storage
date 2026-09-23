<?php

namespace App\Secrets\Strategies;

use App\Enums\SecretType;
use App\Http\Requests\StoreSecretRequest;
use App\Models\Secret;
use App\Secrets\Concerns\BuildsSecretTypeContract;
use App\Secrets\Contracts\SecretTypeStrategy;

final class NoteStrategy implements SecretTypeStrategy
{
    use BuildsSecretTypeContract;

    public function type(): SecretType
    {
        return SecretType::Note;
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
            'value.required' => 'Введите заметку',
            'files.prohibited' => 'Для Secure Note нельзя прикрепить файлы',
            'description.prohibited' => 'Для Secure Note нельзя указать открытое описание',
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
                'widget' => 'textarea',
                'label' => 'Secure Note',
                'hint' => 'В списке скрыта, как пароль. Показать можно только через reveal',
            ],
        ]);
    }
}
