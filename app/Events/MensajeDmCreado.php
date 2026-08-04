<?php

namespace App\Events;

use App\Models\Conversacion;
use App\Models\Mensaje;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MensajeDmCreado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversacion $conversacion,
        public Mensaje $mensaje,
    ) {
        $this->mensaje->loadMissing('user:id,name,username');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversacion.'.$this->conversacion->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mensaje.creado';
    }

    public function broadcastWith(): array
    {
        return [
            'mensaje' => $this->mensaje->toPayload(),
        ];
    }
}
