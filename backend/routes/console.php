<?php

use Illuminate\Support\Facades\Schedule;

// Mantiene fresca la copia local en segundo plano, dentro del presupuesto de peticiones.
Schedule::command('futbol:sincronizar-ligas')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('futbol:refrescar-vencidas')->hourly()->withoutOverlapping();

// Cada inicio de sesión crea un token de acceso: se borran los revocados y vencidos.
Schedule::command('passport:purge')->dailyAt('04:00');
