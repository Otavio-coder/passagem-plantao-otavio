<?php

namespace Tests\Unit;

use App\Models\EMR\CPOE\Appointment;
use App\Repositories\EMR\PatientSurgeryRepository;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class PatientSurgeryRepositoryTest extends TestCase
{
    #[Test]
    public function map_surgery_appointment_uses_real_agenda_status_and_cleans_suffix(): void
    {
        $repository = new PatientSurgeryRepository;

        $appointment = new Appointment;
        $appointment->forceFill([
            'ie_status_agenda' => 'CR',
            'ds_status_agenda_label' => 'Cirurgia realizada', // domain 83 label from SQL addSelect
            'ds_carater_label' => 'Urgência',                // domain 1016 label from SQL addSelect
            'ie_carater_cirurgia' => 'U',
            'dt_agenda' => '2026-04-02',
            'hr_inicio' => '2026-04-02 18:30:00',
            'cd_procedimento' => '31103030',
            'ie_origem_proced' => '5',
        ]);

        $method = new ReflectionMethod(PatientSurgeryRepository::class, 'mapSurgeryAppointment');
        $method->setAccessible(true);

        $mapped = $method->invoke($repository, $appointment, 'Biópsia Endoscópica De Bexiga (Cirurgia realizada)');

        $this->assertSame('Biópsia Endoscópica De Bexiga', $mapped['descricao']);
        $this->assertSame('Cirurgia realizada', $mapped['status']);

        $appointment->forceFill(['ie_status_agenda' => 'N', 'ds_status_agenda_label' => 'Normal']);
        $mappedNormal = $method->invoke($repository, $appointment, 'Dissecção De Veia Para Colocação De Cateter Central Npp Ou Qt');

        $this->assertSame('Normal', $mappedNormal['status']);
    }
}
