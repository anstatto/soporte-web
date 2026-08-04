<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Concerns\BroadcastsImmediately;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class TicketCreatedNotification extends Notification
{
    use BroadcastsImmediately, Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'ticket_created';
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva solicitud de soporte')
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Se ha creado una nueva solicitud: '.$this->ticket->titulo)
            ->action('Ver solicitud', url('/tickets/'.$this->ticket->id));
    }

    public function toArray($notifiable): array
    {
        $by = Auth::user()?->name ?? 'Sistema';

        return [
            'type' => 'ticket_created',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->titulo,
            'assigned_by' => $by,
            'by' => $by,
            'message' => "Nueva solicitud de {$by}",
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return $this->immediateBroadcast($this->toArray($notifiable));
    }
}
