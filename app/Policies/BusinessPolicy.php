<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Business $business): bool
    {
        return $business->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'admin', 'super_admin'], true);
    }

    public function update(User $user, Business $business): bool
    {
        return $business->created_by === $user->id;
    }

    public function delete(User $user, Business $business): bool
    {
        return $business->created_by === $user->id;
    }
}
