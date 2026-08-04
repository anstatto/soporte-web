<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Concerns\BroadcastsImmediately;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TicketMessageNotification extends Notification
{
    use BroadcastsImmediately, Queueable;

    public function __construct(
        protected Ticket $ticket,
        protected ?string $excerpt = null,
        protected bool $hasImage = false,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'ticket_message';
    }

    public function toArray($notifiable): array
    {
        $by = Auth::user()?->name ?? 'Alguien';
        $excerpt = $this->excerpt
            ? Str::limit($this->excerpt, 100)
            : ($this->hasImage ? 'Envió una captura' : 'Nuevo mensaje');

        return [
            'type' => 'ticket_message',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->titulo,
            'by' => $by,
            'assigned_by' => $by,
            'excerpt' => $excerpt,
            'message' => "Nuevo mensaje de {$by}",
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return $this->immediateBroadcast($this->toArray($notifiable));
    }
}
