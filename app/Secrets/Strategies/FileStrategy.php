<?php

namespace App\Secrets\Strategies;

use App\Enums\SecretType;
use App\Http\Requests\StoreSecretRequest;
use App\Models\Secret;
use App\Models\SecretFile;
use App\Secrets\Concerns\BuildsSecretTypeContract;
use App\Secrets\Contracts\SecretTypeStrategy;

final class FileStrategy implements SecretTypeStrategy
{
    use BuildsSecretTypeContract;

    public function type(): SecretType
    {
        return SecretType::File;
    }

    public function rules(): array
    {
        return [
            'value' => ['prohibited'],
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:25600'],
            'description' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.prohibited' => 'Для File нельзя указать секретное значение — используйте описание',
            'files.required' => 'Прикрепите хотя бы один файл',
            'files.min' => 'Прикрепите хотя бы один файл',
            'files.max' => 'Можно прикрепить не больше 20 файлов',
            'files.*.file' => 'Каждое вложение должно быть файлом',
            'files.*.max' => 'Файл не больше 25 МБ',
            'description.max' => 'Описание не длиннее 4096 символов',
        ];
    }

    public function persist(Secret $secret, StoreSecretRequest $request): void
    {
        $secret->forceFill([
            'description' => $request->description(),
        ])->save();

        foreach ($request->uploadedFiles() as $uploaded) {
            SecretFile::storeForSecret($secret, $uploaded);
        }
    }

    public function contract(): array
    {
        return $this->makeContract([
            [
                'name' => 'files',
                'required' => true,
                'widget' => 'files',
                'label' => 'Файлы',
                'hint' => 'Несколько файлов, до 25 МБ каждый',
            ],
            [
                'name' => 'description',
                'required' => false,
                'widget' => 'textarea',
                'label' => 'Описание',
                'hint' => 'Необязательно. Видно в списке всем, у кого есть доступ к секрету',
            ],
        ]);
    }
}
