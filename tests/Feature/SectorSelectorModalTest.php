<?php

namespace Tests\Feature;

use App\Livewire\SectorSelectorModal;
use App\Models\System\User;
use App\Models\System\UserSectorPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class SectorSelectorModalTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<int, array<string, string>>> */
    private array $fakeSectors = [
        '1' => [
            ['sector_code' => '101', 'sector_name' => 'UTI Adulto', 'hospital_code' => '1', 'hospital_name' => 'Hospital Central'],
            ['sector_code' => '102', 'sector_name' => 'Clínica Médica', 'hospital_code' => '1', 'hospital_name' => 'Hospital Central'],
        ],
        '2' => [
            ['sector_code' => '201', 'sector_name' => 'Pediatria', 'hospital_code' => '2', 'hospital_name' => 'Hospital Norte'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Pre-fill cache so getSectorsByHospital() skips Oracle queries entirely
        Cache::put('sectors_for_modal_v1', $this->fakeSectors, 3600);
    }

    public function test_modal_shows_automatically_when_user_has_no_preferences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->assertSet('show', true);
    }

    public function test_modal_is_hidden_when_user_already_has_preferences(): void
    {
        $user = User::factory()->create();
        UserSectorPreference::create([
            'user_id' => $user->id,
            'sector_code' => '101',
            'sector_name' => 'UTI Adulto',
            'hospital_code' => '1',
            'hospital_name' => 'Hospital Central',
        ]);

        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->assertSet('show', false);
    }

    public function test_toggle_sector_adds_sector_to_selected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->call('toggleSector', '101')
            ->assertSet('selectedSectors', ['101']);
    }

    public function test_toggle_sector_removes_already_selected_sector(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->call('toggleSector', '101')
            ->call('toggleSector', '101')
            ->assertSet('selectedSectors', []);
    }

    public function test_save_preferences_persists_to_mysql(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->call('toggleSector', '101')
            ->call('toggleSector', '201')
            ->call('savePreferences')
            ->assertSet('show', false);

        $this->assertDatabaseHas('user_sector_preferences', [
            'user_id' => $user->id,
            'sector_code' => '101',
            'sector_name' => 'UTI Adulto',
            'hospital_code' => '1',
            'hospital_name' => 'Hospital Central',
        ]);

        $this->assertDatabaseHas('user_sector_preferences', [
            'user_id' => $user->id,
            'sector_code' => '201',
            'sector_name' => 'Pediatria',
            'hospital_code' => '2',
            'hospital_name' => 'Hospital Norte',
        ]);

        $this->assertSame(2, UserSectorPreference::where('user_id', $user->id)->count());
    }

    public function test_save_preferences_replaces_existing_preferences(): void
    {
        $user = User::factory()->create();
        UserSectorPreference::create([
            'user_id' => $user->id,
            'sector_code' => '102',
            'sector_name' => 'Clínica Médica',
            'hospital_code' => '1',
            'hospital_name' => 'Hospital Central',
        ]);

        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->call('toggleSector', '101')
            ->call('savePreferences');

        $this->assertDatabaseMissing('user_sector_preferences', [
            'user_id' => $user->id,
            'sector_code' => '102',
        ]);
        $this->assertDatabaseHas('user_sector_preferences', [
            'user_id' => $user->id,
            'sector_code' => '101',
        ]);
    }

    public function test_save_preferences_does_nothing_when_no_sectors_selected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->call('savePreferences');

        $this->assertDatabaseEmpty('user_sector_preferences');
    }

    public function test_select_all_from_hospital_toggles_all_sectors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->call('selectAllFromHospital', '1')
            ->assertSet('selectedSectors', ['101', '102']);
    }

    public function test_select_all_from_hospital_deselects_when_all_already_selected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SectorSelectorModal::class)
            ->call('selectAllFromHospital', '1')
            ->call('selectAllFromHospital', '1')
            ->assertSet('selectedSectors', []);
    }
}
