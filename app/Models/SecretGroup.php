<?php

namespace App\Models;

use App\Models\Concerns\HasAccessTags;
use Database\Factories\SecretGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class SecretGroup extends Model
{
    /** @use HasFactory<SecretGroupFactory> */
    use HasAccessTags, HasFactory;

    /**
     * @return HasMany<Secret, $this>
     */
    public function secrets(): HasMany
    {
        return $this->hasMany(Secret::class);
    }
}
