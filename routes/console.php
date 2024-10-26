<?php

use Illuminate\Support\Facades\Schedule;

// Comando para mostrar una cita inspiradora
// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

// Programar el comando para enviar recordatorios de tickets
Schedule::command('tickets:send-reminders')->everyMinute();
