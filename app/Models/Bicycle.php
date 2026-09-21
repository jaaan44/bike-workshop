<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Every booking ever placed for this bicycle, regardless of status —
     * the authoritative source a bicycle's service history is derived
     * from (no separate history table exists, or is needed).
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Completed repair jobs for this bicycle — the historical service
     * record — newest first. A booking's status only reaches Completed
     * once it has fully passed through the workshop lifecycle, so this
     * never includes cancelled, abandoned, or still-in-progress work.
     *
     * @return HasMany<Booking, $this>
     */
    public function completedBookings(): HasMany
    {
        return $this->bookings()
            ->where('status', BookingStatus::Completed)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * Bookings for this bicycle that haven't reached a terminal status yet
     * (i.e. not Completed or Cancelled) — the bicycle's current, unfinished
     * repair(s), kept separate from its completed service history.
     *
     * @return HasMany<Booking, $this>
     */
    public function activeBookings(): HasMany
    {
        return $this->bookings()
            ->whereNotIn('status', [BookingStatus::Completed, BookingStatus::Cancelled])
            ->orderByDesc('updated_at');
    }
}
