<?php

namespace App\Models;

use App\Services\ShiftService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HandoverActivityLog extends Model
{
    public const EVENT_MODAL_OPEN = 'modal_open';

    public const EVENT_CHAT_POST = 'chat_post';

    public const EVENT_REPORT_OPEN = 'report_open';

    public const EVENT_ANALYSIS_OPEN = 'analysis_open';

    /** @var array<string, string> */
    public const EVENT_LABELS = [
        self::EVENT_REPORT_OPEN => 'Abriu relatório de pendências',
        self::EVENT_ANALYSIS_OPEN => 'Abriu análise do atendimento',
        self::EVENT_MODAL_OPEN => 'Abriu modal do paciente',
    ];

    public $timestamps = false;

    protected $table = 'handover_activity_log';

    protected $fillable = [
        'user_id',
        'nr_atendimento',
        'sector_id',
        'sector_name',
        'event',
        'only_assigned_beds',
        'shift',
        'shift_date',
        'occurred_at',
    ];

    protected $casts = [
        'only_assigned_beds' => 'boolean',
        'occurred_at' => 'datetime',
        'shift_date' => 'date',
    ];

    public static function record(string $event, int $userId, ?int $nrAtendimento = null, ?int $sectorId = null, ?string $sectorName = null): void
    {
        try {
            $context = ShiftService::getShiftContextForLog(now());
            $user = Auth::user();

            static::create([
                'user_id' => $userId,
                'nr_atendimento' => $nrAtendimento,
                'sector_id' => $sectorId,
                'sector_name' => $sectorName,
                'event' => $event,
                'only_assigned_beds' => $user?->only_assigned_beds ?? false,
                'shift' => $context['shift'],
                'shift_date' => $context['date'],
                'occurred_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never break main flow
        }
    }
}
