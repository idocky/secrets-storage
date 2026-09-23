<?php

namespace App\Http\Requests;

use App\Support\AccessTags;
use Illuminate\Foundation\Http\FormRequest;

class StoreFileGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:file_groups,name'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Введите название группы',
            'name.unique' => 'Группа с таким названием уже существует',
            'tags.*.max' => 'Тег не длиннее 50 символов',
        ];
    }

    public function name(): string
    {
        return (string) $this->validated('name');
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return AccessTags::normalize($this->validated('tags', []));
    }
}
