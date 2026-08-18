<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['bicycle_id', 'remarks', 'appointment_date'])]
class Booking extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->reference_number ??= static::nextReferenceNumber();
            $booking->status ??= BookingStatus::Pending;
        });
    }

    /**
     * Human-readable reference like "BR-2026-00001", unique per year.
     */
    public static function nextReferenceNumber(): string
    {
        return DB::transaction(function () {
            $year = now()->year;

            $count = static::whereYear('created_at', $year)->lockForUpdate()->count();

            return sprintf('BR-%d-%05d', $year, $count + 1);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Bicycle, $this>
     */
    public function bicycle(): BelongsTo
    {
        return $this->belongsTo(Bicycle::class);
    }

    /**
     * @return BelongsToMany<BicyclePart, $this>
     */
    public function bicycleParts(): BelongsToMany
    {
        return $this->belongsToMany(BicyclePart::class, 'booking_items');
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'appointment_date' => 'date',
        ];
    }
}
