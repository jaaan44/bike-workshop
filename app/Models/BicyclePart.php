<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bicycle_part_category_id', 'name', 'sort_order'])]
class BicyclePart extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<BicyclePartCategory, $this>
     */
    public function bicyclePartCategory(): BelongsTo
    {
        return $this->belongsTo(BicyclePartCategory::class);
    }
}
