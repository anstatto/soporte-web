<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Concerns\BroadcastsImmediately;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class TicketMentionedNotification extends Notification
{
    use BroadcastsImmediately, Queueable;

    public function __construct(protected Ticket $ticket, protected ?string $excerpt = null) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'ticket_mentioned';
    }

    public function toArray($notifiable): array
    {
        $by = Auth::user()?->name ?? 'Alguien';

        return [
            'type' => 'ticket_mentioned',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->titulo,
            'mentioned_by' => $by,
            'by' => $by,
            'assigned_by' => $by,
            'excerpt' => $this->excerpt,
            'message' => "{$by} te mencionó",
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return $this->immediateBroadcast($this->toArray($notifiable));
    }
}
