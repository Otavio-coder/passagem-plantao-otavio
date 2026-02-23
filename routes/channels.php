<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// ── Chat private channel (per patient) ──────────────────────────────────
Broadcast::channel('chat.{nr_atendimento}', function ($user, $nr_atendimento) {

    if (!$user || empty($nr_atendimento)) {
        return false;
    }

    try {
        return [
            'id'   => $user->id,
            'name' => $user->name,
        ];
    } catch (\Exception $e) {
        Log::error('[Chat] Erro na autorização do canal', [
            'channel' => "chat.{$nr_atendimento}",
            'user_id' => $user->id,
            'error'   => $e->getMessage(),
        ]);
        return false;
    }
});

// ── Personal user channel (for notifications) ────────────────────────────
Broadcast::channel('App.Models.System.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
