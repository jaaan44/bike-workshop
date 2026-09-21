<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isTechnician(): bool
    {
        return $this->role === UserRole::Technician;
    }

    /**
     * Staff and technician share the workshop area for the first release.
     */
    public function isWorkshopUser(): bool
    {
        return in_array($this->role, UserRole::workshopRoles(), true);
    }

    /**
     * @return HasMany<Bicycle, $this>
     */
    public function bicycles(): HasMany
    {
        return $this->hasMany(Bicycle::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Technician notes this user has authored. technician_notes.user_id has
     * no nullOnDelete/cascadeOnDelete (unlike every other author-type FK in
     * the schema), so this is used to block self-service account deletion
     * with a clear message instead of letting the delete fail on a raw DB
     * constraint violation — see ProfileController::destroy().
     *
     * @return HasMany<TechnicianNote, $this>
     */
    public function technicianNotes(): HasMany
    {
        return $this->hasMany(TechnicianNote::class);
    }
}
