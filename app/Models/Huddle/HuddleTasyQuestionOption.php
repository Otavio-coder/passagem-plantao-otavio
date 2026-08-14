<?php

namespace App\Models\Huddle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opção de resposta de uma pergunta do Huddle, espelhada do Tasy (QUE_QUESTAO_OPCAO).
 */
class HuddleTasyQuestionOption extends Model
{
    protected $table = 'huddle_tasy_question_options';

    protected $fillable = [
        'tasy_seq',
        'question_tasy_seq',
        'label',
        'display_order',
        'color',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(HuddleTasyQuestion::class, 'question_tasy_seq', 'tasy_seq');
    }
}
