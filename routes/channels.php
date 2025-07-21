<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{nr_atendimento}.{turno_id}', function ($user, $nr_atendimento, $turno_id) {
    // You can add additional logic here to check if the user is authorized to listen to this channel
    return true; // Allow all users for this example
});

Broadcast::channel('chat.sessao.{sessao_id}', function ($user, $sessao_id) {
    // You can add additional logic here to check if the user is authorized to listen to this channel
    return true; // Allow all users for this example
});
