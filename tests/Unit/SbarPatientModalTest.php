<?php

namespace Tests\Unit;

use App\Livewire\SbarPatientModal;
use App\Services\Tasy\TasyService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class SbarPatientModalTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_uses_pews_for_pediatric_patient_in_scales_data(): void
    {
        $component = new SbarPatientModal;

        $component->patientDetails = (object) [
            'age' => 17,
            'pews_score' => 4,
            'pews_timestamp' => '2026-04-07 10:00:00',
            'pews_classification' => 'Alto',
            'pews_increased' => true,
            'pews_needs_assessment' => false,
            'mews_score' => 1,
            'braden_score' => 12,
            'morse_score' => 18,
            'pain_score' => 3,
            'vte_score' => 2,
        ];

        $scales = $component->getScalesDataProperty();

        $this->assertArrayHasKey('pews', $scales);
        $this->assertArrayNotHasKey('mews', $scales);
        $this->assertSame(4, $scales['pews']['score']);
    }

    #[Test]
    public function it_uses_mews_for_adult_patient_in_scales_data(): void
    {
        $component = new SbarPatientModal;

        $component->patientDetails = (object) [
            'age' => 18,
            'mews_score' => 2,
            'mews_timestamp' => '2026-04-07 10:00:00',
            'mews_classification' => 'Moderado',
            'mews_increased' => false,
            'mews_needs_assessment' => false,
            'pews_score' => 5,
            'braden_score' => 12,
            'morse_score' => 18,
            'pain_score' => 3,
            'vte_score' => 2,
        ];

        $scales = $component->getScalesDataProperty();

        $this->assertArrayHasKey('mews', $scales);
        $this->assertArrayNotHasKey('pews', $scales);
        $this->assertSame(2, $scales['mews']['score']);
    }

    #[Test]
    public function it_removes_duplicate_cid_entries_from_clinical_diagnostics_list(): void
    {
        $component = new SbarPatientModal;

        $component->patientDetails = (object) [
            'diagnosticos_comorbidades' => 'D729 D72.9 Transt NE dos globulos brancos (Principal) | D729 D72.9 Transt NE dos globulos brancos (Principal) | E119 E11.9 Diabetes mellitus tipo 2',
            'dispositivos' => 'Nenhum dispositivo registrado',
            'alergias_detalhadas' => 'Sem alergias registradas',
            'medida_bloqueio' => 'Não',
            'motivos_isolamento' => 'Nenhum motivo de isolamento',
            'materiais' => 'Nenhum antimicrobiano',
            'procedimentos_cirurgicos' => [],
            'avaliacao_enf' => 'Não realizada',
            'plano_educ' => 'Não realizado',
            'pe_data' => 'Não realizado',
            'ds_queda' => 'Não avaliado',
        ];

        $clinicalData = $component->getClinicalDataProperty();

        $this->assertSame([
            'D729 D72.9 Transt NE dos globulos brancos (Principal)',
            'E119 E11.9 Diabetes mellitus tipo 2',
        ], $clinicalData['diagnosticos_list']);
    }

    #[Test]
    public function it_builds_modal_patient_list_and_tracks_current_index(): void
    {
        $component = new SbarPatientModal;

        $setModalPatients = new ReflectionMethod(SbarPatientModal::class, 'setModalPatients');
        $setModalPatients->setAccessible(true);
        $setModalPatients->invoke($component, [
            [
                'nr_atendimento' => 1001,
                'nm_pessoa_fisica' => 'Paciente Um',
                'cd_unidade_basica' => 'A1',
            ],
            [
                'nr_atendimento' => 1002,
                'nm_social' => 'Paciente Dois Social',
                'nm_pessoa_fisica' => 'Paciente Dois',
                'cd_unidade_basica' => 'A2',
            ],
            [
                'nr_atendimento' => 1002,
                'nm_pessoa_fisica' => 'Duplicado',
                'cd_unidade_basica' => 'A2',
            ],
        ], 1002);

        $this->assertCount(2, $component->modalPatients);
        $this->assertSame(1, $component->currentPatientIndex);
        $this->assertSame('1001 - Leito A1 - Paciente Um', $component->modalPatients[0]['label']);
        $this->assertSame('1002 - Leito A2 - Paciente Dois Social', $component->modalPatients[1]['label']);
        $this->assertTrue($component->canGoPrevious);
        $this->assertFalse($component->canGoNext);
    }

    #[Test]
    public function it_falls_back_to_current_attendance_when_modal_list_is_empty(): void
    {
        $component = new SbarPatientModal;

        $setModalPatients = new ReflectionMethod(SbarPatientModal::class, 'setModalPatients');
        $setModalPatients->setAccessible(true);
        $setModalPatients->invoke($component, [], 9999);

        $this->assertSame([
            [
                'nr_atendimento' => 9999,
                'label' => 'Atendimento 9999',
                'cd_setor_atendimento' => 0,
                'cd_unidade_basica' => null,
                'ds_setor_atendimento' => null,
                'ds_prescricao' => null,
            ],
        ], $component->modalPatients);
        $this->assertSame(0, $component->currentPatientIndex);
        $this->assertFalse($component->canGoPrevious);
        $this->assertFalse($component->canGoNext);
    }

    #[Test]
    public function it_preserves_existing_labels_when_reusing_modal_patient_list(): void
    {
        $component = new SbarPatientModal;

        $setModalPatients = new ReflectionMethod(SbarPatientModal::class, 'setModalPatients');
        $setModalPatients->setAccessible(true);
        $setModalPatients->invoke($component, [
            [
                'nr_atendimento' => 1001,
                'label' => '1001 - Leito A1 - Paciente Um',
            ],
            [
                'nr_atendimento' => 1002,
                'label' => '1002 - Leito A2 - Paciente Dois',
            ],
        ], 1002);

        $this->assertSame('1001 - Leito A1 - Paciente Um', $component->modalPatients[0]['label']);
        $this->assertSame('1002 - Leito A2 - Paciente Dois', $component->modalPatients[1]['label']);
    }

    #[Test]
    public function it_reopens_active_alerts_modal_when_requested(): void
    {
        $component = new SbarPatientModal;
        $component->patientAlerts = [
            [
                'type' => 'ALERTA',
                'severity' => 'danger',
                'end_date' => null,
            ],
        ];
        $component->showAlertsModal = false;

        $component->openAlertsModal();

        $this->assertTrue($component->showAlertsModal);
    }

    #[Test]
    public function it_populates_sector_fields_from_patients_list_on_setModalPatients(): void
    {
        $component = new SbarPatientModal;

        $setModalPatients = new ReflectionMethod(SbarPatientModal::class, 'setModalPatients');
        $setModalPatients->setAccessible(true);
        $setModalPatients->invoke($component, [
            [
                'nr_atendimento' => 12345,
                'cd_unidade_basica' => 'UTI-05',
                'ds_setor_atendimento' => 'UTI Adulto',
                'ds_prescricao' => 'Prescrição UTI Adulto',
                'cd_setor_atendimento' => 42,
            ],
        ], 12345);

        $entry = $component->modalPatients[0];
        $this->assertSame('UTI-05', $entry['cd_unidade_basica']);
        $this->assertSame('UTI Adulto', $entry['ds_setor_atendimento']);
        $this->assertSame('Prescrição UTI Adulto', $entry['ds_prescricao']);
        $this->assertSame(42, $entry['cd_setor_atendimento']);
    }
}
