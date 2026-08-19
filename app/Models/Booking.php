<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
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

    /**
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    /**
     * @return HasOne<RepairInspection, $this>
     */
    public function inspection(): HasOne
    {
        return $this->hasOne(RepairInspection::class);
    }

    /**
     * @return HasMany<RepairItem, $this>
     */
    public function repairItems(): HasMany
    {
        return $this->hasMany(RepairItem::class)->oldest('id');
    }

    /**
     * @return HasMany<TechnicianNote, $this>
     */
    public function technicianNotes(): HasMany
    {
        return $this->hasMany(TechnicianNote::class)->latest('id');
    }

    /**
     * Update the status and record who changed it, atomically.
     */
    public function transitionTo(BookingStatus $status): void
    {
        DB::transaction(function () use ($status): void {
            $this->statusHistories()->create([
                'old_status' => $this->status,
                'new_status' => $status,
                'changed_by' => Auth::id(),
            ]);

            // status is deliberately excluded from Fillable (it must never
            // be settable via mass assignment from a request), so it's set
            // directly here rather than through update().
            $this->status = $status;
            $this->save();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'appointment_date' => 'date',
        ];
    }
}
