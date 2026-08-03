<?php

namespace App\Models\Huddle;

use App\Enums\Huddle\DayColor;
use App\Enums\Huddle\HuddleChecklistItem;
use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resposta de um item do checklist do Huddle para um dia de paciente.
 *
 * signal (Red/Green) é o que define a cor do dia: se qualquer item estiver Red,
 * o HuddlePatientDay fica Red (ver HuddlePatientDay::deriveColorFromChecklist).
 */
class HuddleChecklistAnswer extends Model
{
    protected $table = 'huddle_checklist_answers';

    protected $fillable = [
        'huddle_patient_day_id',
        'item_code',
        'answer',
        'signal',
        'responsible',
        'due_at',
        'notes',
        'answered_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'item_code' => HuddleChecklistItem::class,
            'signal' => DayColor::class,
            'due_at' => 'date',
        ];
    }

    public function patientDay(): BelongsTo
    {
        return $this->belongsTo(HuddlePatientDay::class, 'huddle_patient_day_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by_user_id');
    }

    public function isRed(): bool
    {
        return $this->signal === DayColor::Red;
    }
}
