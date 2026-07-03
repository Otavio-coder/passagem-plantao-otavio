<?php

namespace Tests\Feature;

use App\Livewire\SbarPatientModal;
use App\Models\System\User;
use App\Services\Tasy\TasyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class SbarPatientModalTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function navigationList(): array
    {
        return [
            [
                'nr_atendimento' => 111,
                'cd_setor_atendimento' => 101,
                'cd_unidade_basica' => 'L01',
                'ds_setor_atendimento' => 'UTI Adulto',
                'ds_prescricao' => 'UTI',
                'cd_pessoa_fisica' => 555,
                'nm_pessoa_fisica' => 'Paciente Um',
                'nm_social' => null,
                'internment_days' => 3,
            ],
            [
                'nr_atendimento' => 222,
                'cd_setor_atendimento' => 101,
                'cd_unidade_basica' => 'L02',
                'ds_setor_atendimento' => 'UTI Adulto',
                'ds_prescricao' => 'UTI',
                'cd_pessoa_fisica' => 666,
                'nm_pessoa_fisica' => 'Paciente Dois',
                'nm_social' => null,
                'internment_days' => 1,
            ],
        ];
    }

    private function mockTasy(): void
    {
        $this->mock(TasyService::class, function ($mock) {
            $mock->shouldReceive('getSbarPatientDetails')->andReturn((object) [
                'cd_pessoa_fisica' => 555,
                'nm_pessoa_fisica' => 'Paciente Um Completo',
                'alerts' => [],
            ]);
            $mock->shouldReceive('getTherapeuticPlan')->andReturn([]);
            $mock->shouldReceive('getMedicationSchedule')->andReturn([]);
        });
    }

    public function test_open_modal_paints_immediately_without_loading_details(): void
    {
        $this->mockTasy();
        $this->actingAs(User::factory()->create());

        Livewire::test(SbarPatientModal::class)
            ->dispatch('openModal', attendanceNumber: 111, sectorId: 101, hospital: 'HC', patients: $this->navigationList())
            ->assertSet('showModal', true)
            ->assertSet('loadingPatient', true)
            ->assertSet('patientDetails', null)
            ->assertSet('currentPatient.nm_pessoa_fisica', 'Paciente Um')
            ->assertSet('currentPatient.cd_pessoa_fisica', 555)
            ->assertSet('currentPatient.internment_days', 3);
    }

    public function test_deferred_load_fills_details_after_first_paint(): void
    {
        $this->mockTasy();
        $this->actingAs(User::factory()->create());

        Livewire::test(SbarPatientModal::class)
            ->dispatch('openModal', attendanceNumber: 111, sectorId: 101, hospital: 'HC', patients: $this->navigationList())
            ->call('loadDeferredPatientData')
            ->assertSet('loadingPatient', false)
            ->assertSet('currentPatient.nm_pessoa_fisica', 'Paciente Um Completo')
            ->assertSet('planLoaded', true);
    }

    public function test_deferred_load_is_idempotent(): void
    {
        $this->mockTasy();
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(SbarPatientModal::class)
            ->dispatch('openModal', attendanceNumber: 111, sectorId: 101, hospital: 'HC', patients: $this->navigationList())
            ->call('loadDeferredPatientData');

        // Segunda chamada (x-init re-disparado por morph) não deve recarregar nada
        $component->call('loadDeferredPatientData')
            ->assertSet('loadingPatient', false);
    }

    public function test_patient_switch_resets_to_deferred_loading_state(): void
    {
        $this->mockTasy();
        $this->actingAs(User::factory()->create());

        Livewire::test(SbarPatientModal::class)
            ->dispatch('openModal', attendanceNumber: 111, sectorId: 101, hospital: 'HC', patients: $this->navigationList())
            ->call('loadDeferredPatientData')
            ->call('goToNextPatient')
            ->assertSet('currentPatient.nr_atendimento', 222)
            ->assertSet('loadingPatient', true)
            ->assertSet('patientDetails', null)
            ->assertSet('currentPatient.nm_pessoa_fisica', 'Paciente Dois');
    }
}
