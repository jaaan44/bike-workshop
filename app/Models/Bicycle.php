<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bicycle_type_id', 'nickname', 'brand', 'model', 'color', 'wheel_size', 'serial_number', 'year', 'notes'])]
class Bicycle extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BicycleType, $this>
     */
    public function bicycleType(): BelongsTo
    {
        return $this->belongsTo(BicycleType::class);
    }
}
