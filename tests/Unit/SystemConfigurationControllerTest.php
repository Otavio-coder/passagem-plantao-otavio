<?php

namespace Tests\Unit;

use App\Http\Controllers\SystemConfigurationController;
use App\Models\System\UserSectorPreference;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class SystemConfigurationControllerTest extends TestCase
{
    public function test_build_hospital_sections_marks_selected_sectors(): void
    {
        $controller = new SystemConfigurationController;

        $sectorsByHospital = new Collection([
            '1' => collect([
                [
                    'hospital_code' => '1',
                    'hospital_name' => 'Hospital Central',
                    'sector_code' => 'A1',
                    'sector_name' => 'UTI Adulto',
                ],
                [
                    'hospital_code' => '1',
                    'hospital_name' => 'Hospital Central',
                    'sector_code' => 'A2',
                    'sector_name' => 'Clínica Médica',
                ],
            ]),
        ]);

        $method = new ReflectionMethod(SystemConfigurationController::class, 'buildHospitalSections');
        $method->setAccessible(true);

        /** @var array<int, array<string, mixed>> $sections */
        $sections = $method->invoke($controller, $sectorsByHospital, ['A2']);

        $this->assertCount(1, $sections);
        $this->assertSame('1', $sections[0]['code']);
        $this->assertSame(1, $sections[0]['selected_count']);
        $this->assertTrue($sections[0]['is_expanded']);
        $this->assertFalse($sections[0]['sectors'][0]['is_checked']);
        $this->assertTrue($sections[0]['sectors'][1]['is_checked']);
    }

    public function test_build_hospital_sections_marks_selected_sectors_when_sector_code_types_differ(): void
    {
        $controller = new SystemConfigurationController;

        $sectorsByHospital = new Collection([
            '1' => collect([
                [
                    'hospital_code' => '1',
                    'hospital_name' => 'Hospital Central',
                    'sector_code' => '101',
                    'sector_name' => 'UTI Adulto',
                ],
            ]),
        ]);

        $method = new ReflectionMethod(SystemConfigurationController::class, 'buildHospitalSections');
        $method->setAccessible(true);

        /** @var array<int, array<string, mixed>> $sections */
        $sections = $method->invoke($controller, $sectorsByHospital, [101]);

        $this->assertSame(1, $sections[0]['selected_count']);
        $this->assertTrue($sections[0]['sectors'][0]['is_checked']);
    }

    public function test_merge_available_with_user_preferences_keeps_saved_sectors_not_in_available_list(): void
    {
        $controller = new SystemConfigurationController;

        $availableSectors = [
            [
                'sector_code' => 'A1',
                'sector_name' => 'UTI Adulto',
                'hospital_code' => '1',
                'hospital_name' => 'Hospital Central',
            ],
        ];

        $userPreferences = new Collection([
            'Z9' => new UserSectorPreference([
                'sector_code' => 'Z9',
                'sector_name' => 'Setor Histórico',
                'hospital_code' => '9',
                'hospital_name' => 'Hospital Antigo',
            ]),
        ]);

        $method = new ReflectionMethod(SystemConfigurationController::class, 'mergeAvailableWithUserPreferences');
        $method->setAccessible(true);

        /** @var array<int, array{sector_code: string, sector_name: string, hospital_code: string, hospital_name: string}> $merged */
        $merged = $method->invoke($controller, $availableSectors, $userPreferences);

        $codes = array_column($merged, 'sector_code');

        $this->assertContains('A1', $codes);
        $this->assertContains('Z9', $codes);
    }
}
