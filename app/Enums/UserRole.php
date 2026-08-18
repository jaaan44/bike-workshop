<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Technician = 'technician';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Staff => 'Staff',
            self::Technician => 'Technician',
        };
    }

    /**
     * Roles that operate the workshop side of the app (staff area, overlapping permissions for v1).
     *
     * @return array<int, self>
     */
    public static function workshopRoles(): array
    {
        return [self::Staff, self::Technician];
    }
}
