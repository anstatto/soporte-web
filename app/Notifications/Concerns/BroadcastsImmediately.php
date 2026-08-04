<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\BroadcastMessage;

trait BroadcastsImmediately
{
    protected function immediateBroadcast(array $data): BroadcastMessage
    {
        // Sin queue:work el broadcast no llega a Reverb (QUEUE=database).
        return (new BroadcastMessage($data))->onConnection('sync');
    }
}
