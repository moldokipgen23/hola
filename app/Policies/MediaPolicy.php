<?php

namespace App\Policies;

use App\Models\MediaLibrary;
use App\Models\User;

class MediaPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, MediaLibrary $media): bool
    {
        return $media->user_id === $user->id;
    }

    public function delete(User $user, MediaLibrary $media): bool
    {
        return $media->user_id === $user->id;
    }
}
