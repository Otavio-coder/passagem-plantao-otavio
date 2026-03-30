<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Arquiva mensagens com mais de 30 dias (chat_messages → chat_messages_archive)
// Executa diariamente às 03:00
Schedule::command('chat:cleanup --days=30')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();
