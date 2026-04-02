<?php

namespace Tests\Unit;

use App\Support\PendingEventPresentation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PendingEventPresentationTest extends TestCase
{
    #[Test]
    public function prefers_explicit_execution_sector_label(): void
    {
        $label = PendingEventPresentation::executionSectorLabel([
            'setor_execucao' => 'Hemodinâmica',
            'setor_desc_raw' => 'Enfermaria',
            'local' => 'Sala 1',
        ]);

        $this->assertSame('Hemodinâmica', $label);
    }

    #[Test]
    public function falls_back_to_other_sector_fields(): void
    {
        $label = PendingEventPresentation::executionSectorLabel([
            'setor_execucao' => '',
            'setor_desc_raw' => 'Centro Cirúrgico',
        ]);

        $this->assertSame('Centro Cirúrgico', $label);
    }

    #[Test]
    public function returns_dash_when_no_sector_is_available(): void
    {
        $label = PendingEventPresentation::executionSectorLabel([]);

        $this->assertSame('-', $label);
    }

    #[Test]
    public function builds_a_richer_hemotherapy_description(): void
    {
        $description = PendingEventPresentation::hemotherapyDescription([
            'tipo_label' => 'Concentrado de Hemácias',
            'ds_procedimento_prescrito' => '',
            'ds_observacao' => 'cirurgia às 15h do dia 13/11/25',
            'ds_observacao_proc' => '',
            'ds_horarios' => '',
            'qt_vol_hemocomp' => 500,
            'via_aplicacao' => 'IV',
        ]);

        $this->assertSame(
            'Hemoterapia - Concentrado de Hemácias - 500 mL - IV - cirurgia às 15h do dia 13/11/25',
            $description
        );
    }
}
