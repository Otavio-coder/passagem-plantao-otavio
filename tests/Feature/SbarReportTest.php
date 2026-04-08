<?php

namespace Tests\Feature;

use App\Livewire\SbarReport;
use App\Models\System\User;
use App\Models\System\UserSectorPreference;
use App\Services\Tasy\TasyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbarReportTest extends TestCase
{
    use RefreshDatabase;

    private function mockTasy(): void
    {
        $this->mock(TasyService::class, function ($mock) {
            $mock->shouldReceive('getSectorPatientsForSbar')->andReturn([]);
            $mock->shouldReceive('clearSectorCache')->andReturn(null);
        });
    }

    private function userWithPreferences(): User
    {
        $user = User::factory()->create();

        UserSectorPreference::create([
            'user_id' => $user->id,
            'sector_code' => '101',
            'sector_name' => 'UTI Adulto',
            'hospital_code' => '1',
            'hospital_name' => 'Hospital Central',
        ]);

        UserSectorPreference::create([
            'user_id' => $user->id,
            'sector_code' => '102',
            'sector_name' => 'UTI Pediátrica',
            'hospital_code' => '1',
            'hospital_name' => 'Hospital Central',
        ]);

        UserSectorPreference::create([
            'user_id' => $user->id,
            'sector_code' => '201',
            'sector_name' => 'Clínica Médica',
            'hospital_code' => '2',
            'hospital_name' => 'Hospital Norte',
        ]);

        return $user;
    }

    public function test_hospitals_computed_reads_from_mysql_preferences(): void
    {
        $this->mockTasy();
        $user = $this->userWithPreferences();
        $this->actingAs($user);

        $component = Livewire::test(SbarReport::class);

        $hospitals = $component->get('hospitals');

        $this->assertCount(2, $hospitals);
        $hospitalNames = array_column($hospitals, 'hospital_name');
        $this->assertContains('Hospital Central', $hospitalNames);
        $this->assertContains('Hospital Norte', $hospitalNames);
    }

    public function test_hospitals_returns_empty_when_user_has_no_preferences(): void
    {
        $this->mockTasy();
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(SbarReport::class);

        $this->assertEmpty($component->get('hospitals'));
    }

    public function test_sectors_computed_reads_from_mysql_for_selected_hospital(): void
    {
        $this->mockTasy();
        $user = $this->userWithPreferences();
        $this->actingAs($user);

        $component = Livewire::test(SbarReport::class);

        // Default hospital is first alphabetically (Hospital Central = code 1)
        $sectors = $component->get('sectors');

        $this->assertCount(2, $sectors);
        $sectorNames = array_column($sectors, 'ds_setor_atendimento');
        $this->assertContains('UTI Adulto', $sectorNames);
        $this->assertContains('UTI Pediátrica', $sectorNames);
    }

    public function test_sectors_returns_empty_when_no_hospital_selected(): void
    {
        $this->mockTasy();
        $user = $this->userWithPreferences();
        $this->actingAs($user);

        $component = Livewire::test(SbarReport::class);
        $component->set('selectedHospital', null);

        // Unset the persist cache and re-evaluate
        $this->assertEmpty($component->instance()->sectors());
    }

    public function test_change_hospital_reloads_sectors_from_mysql(): void
    {
        $this->mockTasy();
        $user = $this->userWithPreferences();
        $this->actingAs($user);

        $component = Livewire::test(SbarReport::class);
        $component->call('changeHospital', 2);

        $sectors = $component->get('sectors');

        $this->assertCount(1, $sectors);
        $this->assertSame('Clínica Médica', $sectors[0]['ds_setor_atendimento']);
        $this->assertSame(201, $sectors[0]['cd_setor_atendimento']);
    }

    public function test_change_sector_is_denied_for_unauthorized_sector(): void
    {
        $this->mockTasy();
        $user = $this->userWithPreferences();
        $this->actingAs($user);

        $component = Livewire::test(SbarReport::class);
        $component->call('changeSector', 999);

        $this->assertNotNull($component->get('errorMessage'));
    }

    public function test_change_sector_succeeds_for_authorized_sector(): void
    {
        $this->mockTasy();
        $user = $this->userWithPreferences();
        $this->actingAs($user);

        $component = Livewire::test(SbarReport::class);
        $component->call('changeSector', 101);

        $this->assertSame(101, $component->get('selectedSector'));
        $this->assertNull($component->get('errorMessage'));
    }
}
