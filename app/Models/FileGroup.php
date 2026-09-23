<?php

namespace App\Models;

use App\Models\Concerns\HasAccessTags;
use Database\Factories\FileGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class FileGroup extends Model
{
    /** @use HasFactory<FileGroupFactory> */
    use HasAccessTags, HasFactory;

    /**
     * @return HasMany<File, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }
}
