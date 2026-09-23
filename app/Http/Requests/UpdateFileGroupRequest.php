<?php

namespace App\Http\Requests;

use App\Models\File;
use App\Models\FileGroup;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFileGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $file = $this->route('file');
        $user = $this->user();

        return $user !== null
            && $file instanceof File
            && $file->isVisibleTo($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file_group_id' => ['required', 'integer', 'exists:file_groups,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_group_id.required' => 'Укажите группу',
            'file_group_id.exists' => 'Группа не найдена',
        ];
    }

    public function group(): FileGroup
    {
        return FileGroup::query()->findOrFail($this->validated('file_group_id'));
    }
}
