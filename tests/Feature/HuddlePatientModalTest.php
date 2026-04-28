<?php

namespace Tests\Feature;

use App\Livewire\HuddlePatientModal;
use Livewire\Livewire;
use Tests\TestCase;

class HuddlePatientModalTest extends TestCase
{
    private function makePatient(int $nr, string $bed = 'A1', string $name = 'Paciente Teste'): array
    {
        return [
            'nr_atendimento' => $nr,
            'cd_unidade_basica' => $bed,
            'nm_pessoa_fisica' => $name,
            'nr_prontuario' => '123456',
            'ds_setor_atendimento' => 'UTI',
            'cd_setor_atendimento' => 10,
            'has_patient' => true,
            'internment_days' => 5,
            'is_new_patient' => false,
            'sexo' => 'M',
            'age' => 60,
        ];
    }

    public function test_modal_starts_closed(): void
    {
        $component = Livewire::test(HuddlePatientModal::class);

        $component->assertSet('showModal', false);
        $component->assertSet('currentPatient', []);
    }

    public function test_opens_with_patient_and_sets_state(): void
    {
        $patient = $this->makePatient(1001, 'A1', 'João Silva');

        $component = Livewire::test(HuddlePatientModal::class)
            ->dispatch('openModal', attendanceNumber: 1001, hospital: 'Hospital A', sbarPatient: $patient, patients: [$patient]);

        $component->assertSet('showModal', true);
        $component->assertSet('hospitalName', 'Hospital A');

        $this->assertEquals(1001, $component->get('currentPatient')['nr_atendimento']);
    }

    public function test_navigation_state_with_multiple_patients(): void
    {
        $patients = [
            $this->makePatient(1001, 'A1', 'João'),
            $this->makePatient(1002, 'A2', 'Maria'),
            $this->makePatient(1003, 'A3', 'Pedro'),
        ];

        $component = Livewire::test(HuddlePatientModal::class)
            ->dispatch('openModal', attendanceNumber: 1001, hospital: 'H1', sbarPatient: $patients[0], patients: $patients);

        $component->assertSet('currentPatientIndex', 0);
        $component->assertSet('canGoPrevious', false);
        $component->assertSet('canGoNext', true);
    }

    public function test_navigate_to_next_patient(): void
    {
        $patients = [
            $this->makePatient(1001, 'A1', 'João'),
            $this->makePatient(1002, 'A2', 'Maria'),
        ];

        $component = Livewire::test(HuddlePatientModal::class)
            ->dispatch('openModal', attendanceNumber: 1001, hospital: 'H1', sbarPatient: $patients[0], patients: $patients)
            ->call('goToNextPatient');

        $this->assertEquals(1002, $component->get('currentPatient')['nr_atendimento']);
        $component->assertSet('currentPatientIndex', 1);
        $component->assertSet('canGoPrevious', true);
        $component->assertSet('canGoNext', false);
    }

    public function test_navigate_to_previous_patient(): void
    {
        $patients = [
            $this->makePatient(1001, 'A1', 'João'),
            $this->makePatient(1002, 'A2', 'Maria'),
        ];

        $component = Livewire::test(HuddlePatientModal::class)
            ->dispatch('openModal', attendanceNumber: 1002, hospital: 'H1', sbarPatient: $patients[1], patients: $patients)
            ->call('goToPreviousPatient');

        $this->assertEquals(1001, $component->get('currentPatient')['nr_atendimento']);
        $component->assertSet('currentPatientIndex', 0);
    }

    public function test_navigate_by_attendance_number(): void
    {
        $patients = [
            $this->makePatient(1001, 'A1', 'João'),
            $this->makePatient(1002, 'A2', 'Maria'),
            $this->makePatient(1003, 'A3', 'Pedro'),
        ];

        $component = Livewire::test(HuddlePatientModal::class)
            ->dispatch('openModal', attendanceNumber: 1001, hospital: 'H1', sbarPatient: $patients[0], patients: $patients)
            ->call('goToPatientByAttendance', 1003);

        $this->assertEquals(1003, $component->get('currentPatient')['nr_atendimento']);
        $component->assertSet('currentPatientIndex', 2);
    }

    public function test_filters_out_empty_beds_from_modal_patients(): void
    {
        $emptyBed = [
            'nr_atendimento' => null,
            'cd_unidade_basica' => 'B1',
            'nm_pessoa_fisica' => null,
            'has_patient' => false,
        ];
        $patients = [$this->makePatient(1001), $emptyBed];

        $component = Livewire::test(HuddlePatientModal::class)
            ->dispatch('openModal', attendanceNumber: 1001, hospital: 'H1', sbarPatient: $patients[0], patients: $patients);

        $this->assertCount(1, $component->get('modalPatients'));
    }

    public function test_modal_patients_have_labels(): void
    {
        $patient = $this->makePatient(1001, 'A1', 'João Silva');

        $component = Livewire::test(HuddlePatientModal::class)
            ->dispatch('openModal', attendanceNumber: 1001, hospital: 'H1', sbarPatient: $patient, patients: [$patient]);

        $label = $component->get('modalPatients')[0]['label'] ?? '';
        $this->assertStringContainsString('A1', $label);
        $this->assertStringContainsString('João Silva', $label);
    }

    public function test_close_modal_resets_state(): void
    {
        $patient = $this->makePatient(1001);

        $component = Livewire::test(HuddlePatientModal::class)
            ->dispatch('openModal', attendanceNumber: 1001, hospital: 'H1', sbarPatient: $patient, patients: [$patient])
            ->call('closeModal');

        $component->assertSet('showModal', false);
        $component->assertSet('currentPatient', []);
        $component->assertSet('modalPatients', []);
        $component->assertSet('currentPatientIndex', null);
        $component->assertSet('canGoPrevious', false);
        $component->assertSet('canGoNext', false);
    }
}
