<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// El recordatorio de WhatsApp sale 24 horas antes de cada cita, así que hay
// que mirar cada minuto. Si está apagado en la configuración, el comando no
// hace nada. withoutOverlapping por si una vuelta con muchos envíos tarda más
// de un minuto: la siguiente no debe mandar los mismos mensajes otra vez.
Schedule::command('citas:recordar-whatsapp')
    ->everyMinute()
    ->withoutOverlapping();
