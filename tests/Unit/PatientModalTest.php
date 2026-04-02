<?php

namespace Tests\Unit;

use App\Livewire\PatientModal;
use App\Services\TasyService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class PatientModalTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_sets_cd_pessoa_fisica_from_sbar_payload_in_current_patient(): void
    {
        $attendanceNumber = 12345;
        $personId = 67890;

        $tasyService = Mockery::mock(TasyService::class);
        $tasyService->shouldReceive('getPatientAlerts')
            ->once()
            ->with($attendanceNumber, $personId)
            ->andReturn([]);
        $tasyService->shouldReceive('getTherapeuticPlan')
            ->once()
            ->with($attendanceNumber)
            ->andReturn([]);
        $tasyService->shouldReceive('getMedicationSchedule')
            ->zeroOrMoreTimes()
            ->with($attendanceNumber, Mockery::type('string'))
            ->andReturn([]);

        $component = new PatientModal;
        $component->boot($tasyService);
        $component->currentPatient = [
            'nr_atendimento' => $attendanceNumber,
            'has_patient' => true,
        ];

        $payload = [
            'cd_pessoa_fisica' => $personId,
            'nm_pessoa_fisica' => 'Paciente Teste',
        ];

        $loadFromSbarData = new ReflectionMethod(PatientModal::class, 'loadFromSbarData');
        $loadFromSbarData->setAccessible(true);
        $loadFromSbarData->invoke($component, $payload, $attendanceNumber);

        $this->assertSame($personId, $component->currentPatient['cd_pessoa_fisica']);
    }
}
