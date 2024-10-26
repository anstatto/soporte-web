<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketReminderNotification;

class SendTicketReminders extends Command
{
    protected $signature = 'tickets:send-reminders';
    protected $description = 'Enviar recordatorios de tickets pendientes';

    public function handle()
    {
        // Obtener todos los tickets que tienen un recordatorio establecido
        $tickets = Ticket::whereNotNull('recordatorio')
            ->where('recordatorio', '>=', now())
            ->get();

        foreach ($tickets as $ticket) {
            // Obtener el usuario asignado al ticket
            $user = $ticket->user;

            // Enviar la notificación de recordatorio
            Notification::send($user, new TicketReminderNotification($ticket));
        }

        $this->info('Recordatorios de tickets enviados exitosamente.');
    }
}
