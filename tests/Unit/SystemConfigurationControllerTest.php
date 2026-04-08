<?php

namespace Tests\Unit;

use App\Http\Controllers\SystemConfigurationController;
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
}
