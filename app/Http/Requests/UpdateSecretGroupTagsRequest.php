<?php

namespace App\Http\Requests;

use App\Models\SecretGroup;
use App\Support\AccessTags;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSecretGroupTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('secretGroup');
        $user = $this->user();

        return $user !== null
            && $group instanceof SecretGroup
            && $group->isVisibleTo($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tags' => ['present', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tags.present' => 'Передайте список тегов',
            'tags.*.max' => 'Тег не длиннее 50 символов',
        ];
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return AccessTags::normalize($this->validated('tags', []));
    }
}
