<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationIntervalDescriptorTest extends TestCase
{
    /**
     * Verify that medication frequency includes interval descriptor from INTERVALO_PRESCRICAO table
     * when available, or falls back to CD_INTERVALO code if descriptor not found.
     */
    public function test_medications_query_includes_interval_descriptor(): void
    {
        // This test validates that the medicationsQuery properly JOINs with INTERVALO_PRESCRICAO
        // and brings DS_INTERVALO instead of just CD_INTERVALO raw code.
        // The frequencyPt() function on frontend will then display "8/8h - 8 em 8 horas"
        // instead of just "SN 8/8"

        $repo = app('App\Repositories\EMR\PatientTherapeuticPlanRepository');
        $reflectionClass = new \ReflectionClass($repo);
        $method = $reflectionClass->getMethod('medicationsQuery');
        $method->setAccessible(true);
        $query = $method->invoke($repo);

        // Verify the query includes the INTERVALO_PRESCRICAO LEFT JOIN
        $this->assertStringContainsString(
            'LEFT JOIN tasy.intervalo_prescricao ip',
            $query,
            'Query should LEFT JOIN with INTERVALO_PRESCRICAO table'
        );

        // Verify the query selects DS_INTERVALO with NVL fallback
        $this->assertStringContainsString(
            'NVL(ip.DS_INTERVALO, cm.CD_INTERVALO)',
            $query,
            'Query should select NVL(ip.DS_INTERVALO, cm.CD_INTERVALO) to prefer descriptor with fallback'
        );

        // Verify the JOIN condition checks for active status
        $this->assertStringContainsString(
            "ip.ie_situacao",
            $query,
            'INTERVALO_PRESCRICAO JOIN should only include active records'
        );

        // Verify the column is aliased as secondary_info (which maps to 'frequency' in formatMedication)
        $this->assertStringContainsString(
            'AS secondary_info',
            $query,
            'Query should alias the interval descriptor column as secondary_info'
        );
    }

    /**
     * Verify that formatMedication correctly maps secondary_info to frequency field
     */
    public function test_format_medication_maps_frequency_from_secondary_info(): void
    {
        $repo = app('App\Repositories\EMR\PatientTherapeuticPlanRepository');
        $reflectionClass = new \ReflectionClass($repo);
        $method = $reflectionClass->getMethod('formatMedication');
        $method->setAccessible(true);

        // Test with a mock row that includes DS_INTERVALO descriptor
        $mockRow = (object) [
            'id' => 123,
            'name' => 'Amoxicilina 500mg',
            'qty' => '500',
            'unit_measure' => 'mg',
            'route' => 'Oral',
            'secondary_info' => '8/8h - 8 em 8 horas',
            'schedule' => null,
            'first_hour' => null,
            'flag1' => null,
            'flag2' => 'N',
            'flag3' => null,
            'flag4' => null,
            'diluent_name' => null,
            'diluent_qty' => null,
            'med_volume' => null,
            'infusion_min' => null,
            'prep_notes' => null,
            'observation' => null,
            'note_text' => null,
            'dt_start' => null,
            'dt_end' => null,
            'dt_suspended' => null,
            'num1' => 3,
            'num2' => 2,
            'nr_prescricao' => 999,
        ];

        $formatted = $method->invoke($repo, $mockRow);

        // Verify that frequency field contains the friendly DS_INTERVALO description
        $this->assertEquals(
            '8/8h - 8 em 8 horas',
            $formatted['frequency'],
            'Frequency should contain DS_INTERVALO descriptor "8/8h - 8 em 8 horas"'
        );
    }

    /**
     * Verify fallback to raw code when descriptor not found
     */
    public function test_format_medication_falls_back_to_code_when_descriptor_missing(): void
    {
        $repo = app('App\Repositories\EMR\PatientTherapeuticPlanRepository');
        $reflectionClass = new \ReflectionClass($repo);
        $method = $reflectionClass->getMethod('formatMedication');
        $method->setAccessible(true);

        // Test with raw code when descriptor not found
        $mockRow = (object) [
            'id' => 124,
            'name' => 'Antibiótico XYZ',
            'qty' => '250',
            'unit_measure' => 'mg',
            'route' => 'IV',
            'secondary_info' => 'CD CODE',  // Fallback to raw code
            'schedule' => null,
            'first_hour' => null,
            'flag1' => null,
            'flag2' => 'N',
            'flag3' => null,
            'flag4' => null,
            'diluent_name' => null,
            'diluent_qty' => null,
            'med_volume' => null,
            'infusion_min' => null,
            'prep_notes' => null,
            'observation' => null,
            'note_text' => null,
            'dt_start' => null,
            'dt_end' => null,
            'dt_suspended' => null,
            'num1' => 2,
            'num2' => 1,
            'nr_prescricao' => 888,
        ];

        $formatted = $method->invoke($repo, $mockRow);

        // Verify fallback to raw code
        $this->assertEquals(
            'CD CODE',
            $formatted['frequency'],
            'Frequency should fallback to raw code when descriptor not found'
        );
    }
}
