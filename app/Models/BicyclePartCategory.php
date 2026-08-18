<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sort_order'])]
class BicyclePartCategory extends Model
{
    use HasFactory;

    /**
     * @return HasMany<BicyclePart, $this>
     */
    public function bicycleParts(): HasMany
    {
        return $this->hasMany(BicyclePart::class);
    }
}
