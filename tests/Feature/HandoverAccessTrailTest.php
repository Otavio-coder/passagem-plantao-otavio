<?php

namespace Tests\Feature;

use App\Models\HandoverActivityLog;
use App\Models\System\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandoverAccessTrailTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function opening_pending_report_records_access(): void
    {
        $user = User::factory()->create();

        $this->withoutMiddleware()
            ->actingAs($user)
            ->get(route('pending.report'))
            ->assertOk();

        $this->assertDatabaseHas('handover_activity_log', [
            'user_id' => $user->id,
            'event' => HandoverActivityLog::EVENT_REPORT_OPEN,
            'nr_atendimento' => null,
        ]);
    }

    #[Test]
    public function opening_patient_analysis_records_access(): void
    {
        $user = User::factory()->create();

        $this->withoutMiddleware()
            ->actingAs($user)
            ->getJson(route('metrics.show', ['nr' => 123456]))
            ->assertOk();

        $this->assertDatabaseHas('handover_activity_log', [
            'user_id' => $user->id,
            'event' => HandoverActivityLog::EVENT_ANALYSIS_OPEN,
            'nr_atendimento' => 123456,
        ]);
    }

    #[Test]
    public function record_accepts_events_without_attendance_and_sector(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        HandoverActivityLog::record(HandoverActivityLog::EVENT_REPORT_OPEN, $user->id);

        $this->assertDatabaseHas('handover_activity_log', [
            'user_id' => $user->id,
            'event' => HandoverActivityLog::EVENT_REPORT_OPEN,
            'nr_atendimento' => null,
            'sector_id' => null,
        ]);
    }
}
