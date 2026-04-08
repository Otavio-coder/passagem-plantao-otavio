<?php

namespace Tests\Unit;

use App\Services\Tasy\TasyFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TasyFormatterTest extends TestCase
{
    #[Test]
    public function builds_discharge_info_for_medical_discharge_with_predicted_date(): void
    {
        $result = TasyFormatter::buildDischargeInfo(
            null,
            '2026-04-08 14:30:00',
            '2026-04-09 10:15:00',
            'Melhora clínica'
        );

        $this->assertSame('alta_medica', $result['tipo']);
        $this->assertSame('08/04/2026 14:30', $result['dt_alta_medico_formatted']);
        $this->assertSame('09/04/2026 10:15', $result['dt_previsto_alta_formatted']);
        $this->assertSame('Melhora clínica', $result['ds_motivo_alta']);
    }

    #[Test]
    public function builds_discharge_info_for_effective_discharge(): void
    {
        $result = TasyFormatter::buildDischargeInfo(
            '2026-04-08 16:20:00',
            null,
            null,
            'Alta administrativa'
        );

        $this->assertSame('alta', $result['tipo']);
        $this->assertSame('08/04/2026 16:20', $result['dt_alta_formatted']);
        $this->assertSame('Alta administrativa', $result['ds_motivo_alta']);
    }

    #[Test]
    public function builds_discharge_info_from_bed_using_static_formatter(): void
    {
        $formatter = new TasyFormatter;
        $bed = (object) [
            'dt_alta_medico' => '2026-04-08 08:00:00',
            'apa_dt_previsto_alta' => '2026-04-08 20:00:00',
            'ds_motivo_alta' => 'Observação concluída',
        ];

        $result = $formatter->buildDischargeInfoFromBed($bed);

        $this->assertSame('alta_medica', $result['tipo']);
        $this->assertSame('08/04/2026 08:00', $result['dt_alta_medico_formatted']);
        $this->assertSame('08/04/2026 20:00', $result['dt_previsto_alta_formatted']);
    }
}
