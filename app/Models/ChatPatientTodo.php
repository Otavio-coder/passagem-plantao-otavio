<?php

namespace App\Models;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatPatientTodo extends Model
{
    protected $table = 'chat_patient_todos';

    protected $fillable = [
        'nr_atendimento',
        'created_by_user_id',
        'done_by_user_id',
        'content',
        'is_done',
        'has_alert',
        'shift',
        'done_at',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'has_alert' => 'boolean',
            'done_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }

    public function scopeForPatient($query, int $nrAtendimento)
    {
        return $query->where('nr_atendimento', $nrAtendimento);
    }
}
