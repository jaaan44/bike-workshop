<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sort_order'])]
class BicycleType extends Model
{
    use HasFactory;

    /**
     * @return HasMany<Bicycle, $this>
     */
    public function bicycles(): HasMany
    {
        return $this->hasMany(Bicycle::class);
    }
}
