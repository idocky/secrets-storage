<?php

namespace App\Http\Requests;

use App\Enums\SecretType;
use App\Models\Secret;
use App\Models\SecretGroup;
use App\Secrets\Contracts\SecretTypeStrategy;
use App\Secrets\SecretTypeRegistry;
use App\Support\AccessTags;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $key = $this->input('key');

        if (! is_string($key)) {
            return;
        }

        $this->merge([
            'key' => preg_replace('/\s+/u', ' ', trim($key)) ?? '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', Rule::enum(SecretType::class)],
            'key' => ['required', 'string', 'max:255', 'unique:secrets,key', 'regex:/^[^\p{C}]+$/u'],
            'environment' => ['required', 'array', 'min:1'],
            'environment.*' => ['string', Rule::in(Secret::ENVIRONMENTS)],
            'secret_group_id' => ['nullable', 'integer', 'exists:secret_groups,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];

        $strategy = $this->strategy();

        if ($strategy !== null) {
            $rules = [...$rules, ...$strategy->rules()];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'type.required' => 'Выберите тип секрета',
            'type.enum' => 'Неизвестный тип секрета',
            'key.required' => 'Введите ключ',
            'key.unique' => 'Секрет с таким ключом уже существует',
            'key.regex' => 'Ключ не должен содержать переносы строк и служебные символы',
            'value.max' => 'Значение не длиннее 4096 символов',
            'environment.required' => 'Выберите хотя бы одно окружение',
            'environment.min' => 'Выберите хотя бы одно окружение',
            'environment.*.in' => 'Недопустимое окружение',
            'secret_group_id.exists' => 'Выбранная группа не найдена',
            'tags.*.max' => 'Тег не длиннее 50 символов',
        ];

        $strategy = $this->strategy();

        if ($strategy !== null) {
            $messages = [...$messages, ...$strategy->messages()];
        }

        return $messages;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $groupId = $this->input('secret_group_id');

            if ($groupId === null || $groupId === '') {
                return;
            }

            $group = SecretGroup::query()->find((int) $groupId);
            $user = $this->user();

            if ($group === null || $user === null || ! $group->isVisibleTo($user)) {
                $validator->errors()->add('secret_group_id', 'Выбранная группа не найдена');
            }
        });
    }

    public function secretType(): SecretType
    {
        return SecretType::from((string) $this->validated('type'));
    }

    public function key(): string
    {
        return (string) $this->validated('key');
    }

    public function secretValue(): ?string
    {
        $value = $this->validated('value') ?? null;

        return filled($value) ? (string) $value : null;
    }

    public function description(): ?string
    {
        $description = $this->validated('description') ?? null;

        return filled($description) ? (string) $description : null;
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploadedFiles(): array
    {
        $files = $this->file('files', []);

        if (! is_array($files)) {
            $files = $files !== null ? [$files] : [];
        }

        return array_values(array_filter(
            $files,
            fn ($file): bool => $file instanceof UploadedFile && $file->isValid(),
        ));
    }

    /**
     * @return list<string>
     */
    public function environments(): array
    {
        /** @var list<string> $environment */
        $environment = $this->validated('environment');

        return array_values(array_unique(array_intersect(
            Secret::ENVIRONMENTS,
            $environment,
        )));
    }

    public function secretGroupId(): ?int
    {
        $id = $this->validated('secret_group_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return AccessTags::normalize($this->validated('tags', []));
    }

    private function strategy(): ?SecretTypeStrategy
    {
        $type = SecretType::tryFrom((string) $this->input('type'));

        if ($type === null) {
            return null;
        }

        return app(SecretTypeRegistry::class)->get($type);
    }
}
