<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFilePublicRequest extends FormRequest
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
            'is_public' => ['required', 'boolean'],
        ];
    }

    public function isPublic(): bool
    {
        return $this->boolean('is_public');
    }
}
