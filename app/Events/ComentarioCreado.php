<?php

namespace App\Events;

use App\Models\Comentario;
use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComentarioCreado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Comentario $comentario,
    ) {
        $this->comentario->loadMissing('user:id,name,username');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ticket.'.$this->ticket->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'comentario.creado';
    }

    public function broadcastWith(): array
    {
        return [
            'comentario' => [
                'id' => $this->comentario->id,
                'contenido' => $this->comentario->contenido,
                'imagen' => $this->comentario->imagen,
                'imagen_url' => $this->comentario->imagen_url,
                'user' => $this->comentario->user,
                'user_id' => $this->comentario->user_id,
                'created_at' => $this->comentario->created_at?->toIso8601String(),
            ],
        ];
    }
}
