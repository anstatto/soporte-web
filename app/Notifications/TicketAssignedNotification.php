<?php

namespace App\Notifications;

use App\Notifications\Concerns\BroadcastsImmediately;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class TicketAssignedNotification extends Notification
{
    use BroadcastsImmediately, Queueable;

    public function __construct(protected $ticket) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'ticket_assigned';
    }

    public function toArray($notifiable): array
    {
        $by = Auth::user()?->name ?? 'Sistema';

        return [
            'type' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->titulo,
            'assigned_by' => $by,
            'by' => $by,
            'assigned_to' => $notifiable->name ?? null,
            'message' => "{$by} te asignó un ticket",
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return $this->immediateBroadcast($this->toArray($notifiable));
    }
}
