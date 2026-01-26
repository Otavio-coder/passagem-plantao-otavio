<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Encerra sessões de chat automaticamente a cada mudança de turno
// Executa às 07:15, 13:15 e 19:15 (15 minutos após início de cada turno)
Schedule::command('chat:close-sessions')
    ->dailyAt('07:15')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('chat:close-sessions')
    ->dailyAt('13:15')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('chat:close-sessions')
    ->dailyAt('19:15')
    ->withoutOverlapping()
    ->runInBackground();

// Limpeza de dados antigos do chat (executa diariamente às 03:00)
// Mantém 90 dias de mensagens e 365 dias de auditorias
Schedule::command('chat:cleanup --days=90 --keep-audits=365')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();
