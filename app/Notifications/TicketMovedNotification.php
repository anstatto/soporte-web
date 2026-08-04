<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Concerns\BroadcastsImmediately;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class TicketMovedNotification extends Notification
{
    use BroadcastsImmediately, Queueable;

    public function __construct(
        protected Ticket $ticket,
        protected string $fromEstado,
        protected string $toEstado,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'ticket_moved';
    }

    public function toArray($notifiable): array
    {
        $by = Auth::user()?->name ?? 'Alguien';
        $title = $this->ticket->titulo;

        return [
            'type' => 'ticket_moved',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $title,
            'by' => $by,
            'from_estado' => $this->fromEstado,
            'to_estado' => $this->toEstado,
            'excerpt' => "{$this->fromEstado} → {$this->toEstado}",
            'message' => "{$by} movió «{$title}» a {$this->toEstado}",
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return $this->immediateBroadcast($this->toArray($notifiable));
    }
}
