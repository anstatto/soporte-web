<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TicketAssignedNotification extends Notification
{
    use Queueable;

    protected $ticket;

    /**
     * Create a new notification instance.
     */
    public function __construct($ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('¡Tienes un nuevo ticket asignado!')
                    ->greeting('Hola ' . $notifiable->name . ',')
                    ->line('Te informamos que se te ha asignado un nuevo ticket con el título: ' . $this->ticket->titulo)
                    ->action('Ver Ticket', url('/tickets/' . $this->ticket->id))
                    ->line('Por favor, revisa el ticket y actúa en consecuencia.')
                    ->line('Gracias por ser parte de nuestro equipo y por usar nuestra aplicación.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->titulo,
            'assigned_by' => Auth::user()->name, // Nombre del usuario que asignó el ticket
            'assigned_to' => $notifiable->name,  // Nombre del usuario al que se asignó el ticket
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     *
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'message' => 'Tienes una nueva notificación!',
        ]);
    }
}
