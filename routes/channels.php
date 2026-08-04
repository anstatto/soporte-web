<?php

use App\Models\Conversacion;
use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = Ticket::query()->find($ticketId);
    if (! $ticket) {
        return false;
    }

    return $user->can('view', $ticket);
});

Broadcast::channel('conversacion.{conversacionId}', function ($user, $conversacionId) {
    $conversacion = Conversacion::query()->find($conversacionId);
    if (! $conversacion) {
        return false;
    }

    return $user->can('view', $conversacion);
});
