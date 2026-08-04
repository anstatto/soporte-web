<?php

namespace App\Policies;

use App\Models\Conversacion;
use App\Models\User;

class ConversacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chat with users');
    }

    public function view(User $user, Conversacion $conversacion): bool
    {
        if (! $user->can('chat with users')) {
            return false;
        }

        return $conversacion->users()->where('users.id', $user->id)->exists();
    }

    public function message(User $user, Conversacion $conversacion): bool
    {
        return $this->view($user, $conversacion);
    }
}
