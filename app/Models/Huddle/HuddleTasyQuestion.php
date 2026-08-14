<?php

namespace App\Models\Huddle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pergunta do questionário do Huddle, espelhada do Tasy (QUE_QUESTAO).
 *
 * O `tasy_seq` é o NR_SEQUENCIA da pergunta no Tasy — a chave estável usada para
 * casar respostas e para o upsert do sync (huddle:sync-questions).
 */
class HuddleTasyQuestion extends Model
{
    protected $table = 'huddle_tasy_questions';

    protected $fillable = [
        'questionnaire_seq',
        'tasy_seq',
        'label',
        'answer_type',
        'display_order',
        'green_answer',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(HuddleTasyQuestionOption::class, 'question_tasy_seq', 'tasy_seq');
    }
}
