<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSessao extends Model
{
    protected $table = 'chat_sessoes';

    protected $fillable = [
        'nr_atendimento',
        'turno_id',
        'data_sessao',
        'inicio',
        'fim',
        'encerrada',
    ];

    public function fecharSessao()
    {
        $this->fim = now();
        $this->encerrada = true;
        $this->save();

        \Log::debug('ChatSessao encerrada', [
            'sessao_id' => $this->id,
            'nr_atendimento' => $this->nr_atendimento,
            'turno_id' => $this->turno_id,
            'data_sessao' => $this->data_sessao,
            'encerrada' => $this->encerrada,
        ]);
    }
    
    public $timestamps = true;

    public function mensagens()
    {
        return $this->hasMany(ChatMensagem::class, 'sessao_id');
    }
}