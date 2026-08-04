<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view tickets');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (! $user->can('view tickets')) {
            return false;
        }

        if ($user->esSoporte()) {
            return true;
        }

        return $this->ownsOrAssigned($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->can('create tickets');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if (! $user->can('edit tickets')) {
            return false;
        }

        if ($user->esSoporte()) {
            return true;
        }

        return $ticket->user_id === $user->id;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        if (! $user->can('delete tickets')) {
            return false;
        }

        return $user->esSoporte();
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        if (! $user->can('comment on tickets')) {
            return false;
        }

        return $this->view($user, $ticket);
    }

    public function assign(User $user): bool
    {
        return $user->can('assign tickets') && $user->esSoporte();
    }

    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->esSoporte() && $user->can('edit tickets');
    }

    protected function ownsOrAssigned(User $user, Ticket $ticket): bool
    {
        if ($ticket->user_id === $user->id) {
            return true;
        }

        return $ticket->users()->where('users.id', $user->id)->exists();
    }
}
