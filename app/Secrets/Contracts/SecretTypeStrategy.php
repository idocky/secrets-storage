<?php

namespace App\Secrets\Contracts;

use App\Enums\SecretType;
use App\Http\Requests\StoreSecretRequest;
use App\Models\Secret;

interface SecretTypeStrategy
{
    public function type(): SecretType;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @return array<string, string>
     */
    public function messages(): array;

    public function persist(Secret $secret, StoreSecretRequest $request): void;

    /**
     * @return array{
     *     slug: string,
     *     label: string,
     *     fields: list<array{name: string, required: bool, widget: string, label: string, hint: string|null}>
     * }
     */
    public function contract(): array;
}
