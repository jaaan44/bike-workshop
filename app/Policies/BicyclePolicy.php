<?php

namespace App\Policies;

use App\Models\Bicycle;
use App\Models\User;

class BicyclePolicy
{
    public function view(User $user, Bicycle $bicycle): bool
    {
        return $user->id === $bicycle->user_id;
    }

    public function update(User $user, Bicycle $bicycle): bool
    {
        return $user->id === $bicycle->user_id;
    }
}
