<?php

namespace Tests\Unit;

use App\Repositories\EMR\PatientPrescriptionsRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MedicationIntervalDescriptorTest extends TestCase
{
    /**
     * Verify that the medications query JOINs INTERVALO_PRESCRICAO and returns
     * DS_INTERVALO (e.g. "8/8h - 8 em 8 horas") instead of just the raw CD_INTERVALO code.
     */
    #[Test]
    public function medications_query_includes_interval_descriptor(): void
    {
        $repo = new PatientPrescriptionsRepository;
        $method = new \ReflectionMethod($repo, 'medicationsQuery');
        $method->setAccessible(true);
        $query = $method->invoke($repo);

        $this->assertStringContainsString(
            'LEFT JOIN tasy.intervalo_prescricao ip',
            $query,
            'Query should LEFT JOIN with INTERVALO_PRESCRICAO table'
        );
        $this->assertStringContainsString(
            'NVL(ip.DS_INTERVALO, cm.CD_INTERVALO)',
            $query,
            'Query should prefer DS_INTERVALO with CD_INTERVALO fallback'
        );
        $this->assertStringContainsString(
            'ip.ie_situacao',
            $query,
            'INTERVALO_PRESCRICAO JOIN should filter active records'
        );
        $this->assertStringContainsString(
            'AS secondary_info',
            $query,
            'Interval column should be aliased as secondary_info (mapped to frequency in formatMedication)'
        );
    }

    /**
     * Verify that formatMedication maps secondary_info → frequency with the DS_INTERVALO descriptor.
     */
    #[Test]
    public function format_medication_maps_frequency_from_secondary_info(): void
    {
        $repo = new PatientPrescriptionsRepository;
        $method = new \ReflectionMethod($repo, 'formatMedication');
        $method->setAccessible(true);

        $row = (object) [
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
            'flag5' => null,
            'flag6' => null,
            'diluent_name' => null,
            'diluent_qty' => null,
            'med_volume' => null,
            'infusion_min' => null,
            'prep_notes' => null,
            'observation' => null,
            'note_text' => null,
            'extra1' => null,
            'extra2' => null,
            'dt_start' => null,
            'dt_end' => null,
            'dt_suspended' => null,
            'num1' => 3,
            'num2' => 2,
            'nr_prescricao' => 999,
        ];

        $formatted = $method->invoke($repo, $row);

        $this->assertSame('8/8h - 8 em 8 horas', $formatted['frequency']);
        $this->assertSame(3, $formatted['total_doses']);
        $this->assertSame(2, $formatted['checked_doses']);
        $this->assertSame(999, $formatted['nr_prescricao']);
    }

    /**
     * Verify that formatMedication uses the raw code as frequency when DS_INTERVALO is not available.
     */
    #[Test]
    public function format_medication_falls_back_to_code_when_descriptor_missing(): void
    {
        $repo = new PatientPrescriptionsRepository;
        $method = new \ReflectionMethod($repo, 'formatMedication');
        $method->setAccessible(true);

        $row = (object) [
            'id' => 124,
            'name' => 'Antibiótico XYZ',
            'qty' => '250',
            'unit_measure' => 'mg',
            'route' => 'IV',
            'secondary_info' => 'SN',
            'schedule' => null,
            'first_hour' => null,
            'flag1' => null,
            'flag2' => 'S',
            'flag3' => '5',
            'flag4' => null,
            'flag5' => null,
            'flag6' => null,
            'diluent_name' => null,
            'diluent_qty' => null,
            'med_volume' => null,
            'infusion_min' => null,
            'prep_notes' => null,
            'observation' => null,
            'note_text' => null,
            'extra1' => null,
            'extra2' => null,
            'dt_start' => null,
            'dt_end' => null,
            'dt_suspended' => null,
            'num1' => 2,
            'num2' => 1,
            'nr_prescricao' => 888,
        ];

        $formatted = $method->invoke($repo, $row);

        $this->assertSame('SN', $formatted['frequency']);
        $this->assertTrue($formatted['is_antibiotic']);
        $this->assertSame(5, $formatted['antibiotic_day']);
    }
}
