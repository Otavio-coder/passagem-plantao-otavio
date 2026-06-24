<?php

namespace Tests\Unit;

use App\Services\Tasy\PrescriptionFormatter;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class PatientPrescriptionsRepositoryTest extends TestCase
{
    #[Test]
    public function formats_nutrition_item_with_enriched_plan_fields(): void
    {
        $repository = new PrescriptionFormatter;

        $row = (object) [
            'name' => 'Dieta Enteral',
            'flag1' => 'N',
            'flag3' => 'S',
            'flag4' => 'N',
            'flag5' => 'S',
            'flag6' => 'N',
            'flag7' => 'S',
            'flag8' => 'N',
            'note_text' => 'Alergia a proteína do leite',
            'observation' => 'Manter cabeceira elevada',
            'guidance' => 'Infundir em 20h',
            'justification' => 'Adequação calórica',
            'schedule' => '08:00 16:00',
            'qty' => '250',
            'unit_measure' => 'mL',
            'total_volume' => '1000',
            'infusion_speed' => '50',
            'route' => 'SNE',
            'interval_code' => '6/6h',
            'interval_description' => 'NUT 16',
            'professional_name' => 'Dra. Nutri',
            'dt_start' => '2026-04-02 08:00:00',
            'dt_end' => '2026-04-03 08:00:00',
            'product_1' => 'Fórmula 1',
            'product_1_qty' => '500',
            'product_2' => 'Módulo Proteico',
            'product_2_qty' => '2 sachês',
            'product_3' => null,
            'product_3_qty' => null,
            'product_4' => null,
            'product_4_qty' => null,
            'product_5' => null,
            'product_5_qty' => null,
        ];

        $item = $repository->formatNutritionItem($row);

        $this->assertSame('Enteral', $item['type']);
        $this->assertSame('250 mL', $item['volume']);
        $this->assertSame('1000', $item['total_volume']);
        $this->assertSame('50', $item['infusion_speed']);
        $this->assertSame('SNE', $item['route']);
        $this->assertSame('6/6h', $item['interval_code']);
        $this->assertSame('NUT 16', $item['interval_description']);
        $this->assertSame(['Contínuo', 'Bomba de infusão'], $item['delivery_mode']);
        $this->assertCount(2, $item['products']);
        $this->assertTrue($item['has_details']);
    }

    #[Test]
    public function returns_nutrition_items_without_shift_grouping(): void
    {
        $repository = new PrescriptionFormatter;

        $rowA = new stdClass;
        $rowA->name = 'Dieta A';
        $rowA->flag1 = 'N';
        $rowA->flag3 = 'N';
        $rowA->flag4 = 'N';

        $rowB = new stdClass;
        $rowB->name = 'Dieta B';
        $rowB->flag1 = 'J';
        $rowB->flag3 = 'N';
        $rowB->flag4 = 'N';

        $formatted = $repository->organizeNutritionByShift(collect([$rowA, $rowB]));

        $this->assertSame(2, $formatted['count']);
        $this->assertArrayHasKey('items', $formatted);
        $this->assertCount(2, $formatted['items']);
        $this->assertArrayNotHasKey('shifts', $formatted);
    }

    #[Test]
    public function format_surgery_includes_surgery_type_code(): void
    {
        $repository = new PrescriptionFormatter;

        $row = new stdClass;
        $row->id = 10;
        $row->name = 'Procedimento cirúrgico (Cirurgia realizada)';
        $row->flag1 = 'E';
        $row->status_raw = 'A';
        $row->setor_raw = 1120;
        $row->setor_desc_raw = 'Centro Cirúrgico';
        $row->schedule = '08/04/26 22:00';
        $row->extra1 = '3';
        $row->extra2 = 'Descrição';
        $row->extra3 = 7;
        $row->extra4 = 'Centro Cirúrgico HSR';
        $row->observation = 'Pedido Medico: Com OPME / valor 100';

        $formatted = $repository->formatSurgery($row);

        $this->assertSame('Procedimento cirúrgico', $formatted['name']);
        $this->assertSame(7, $formatted['tipo_cirurgia_codigo']);
        $this->assertSame('Centro Cirúrgico HSR', $formatted['local']);
        $this->assertSame('Pedido Medico: Com OPME /', $formatted['observacoes']);
    }

    #[Test]
    public function format_procedure_maps_status_flags_correctly(): void
    {
        $repository = new PrescriptionFormatter;
        $method = new \ReflectionMethod($repository, 'formatProcedure');

        $base = (object) [
            'id' => 1,
            'name' => 'Hemograma',
            'origem' => 'PRESCRICAO',
            'status_raw' => '10',
            'status_label' => 'Solicitado',
            'scheduled' => '09/04/26 08:00',
            'scheduled_raw' => '2026-04-09 08:00:00',
            'tipo' => 'Exame Laboratorial',
            'professional_name' => 'Dr. Silva',
            'nr_prescricao' => 12345,
            'dt_baixa' => null,
            'dt_solicitacao_raw' => '2026-04-09 07:00:00',
            'dt_liberacao_raw' => '2026-04-09 07:05:00',
            'event_type' => 'exame',
            'sample_check' => 'S',
            'collected_at_raw' => null,
            'ds_grupo_lab' => 'Hematologia',
            'ds_status_laudo' => null,
            'setor_raw' => null,
            'setor_desc_raw' => null,
            'is_today' => 1,
            'is_yesterday' => 0,
            'is_tomorrow' => 0,
            'ds_resultado_laudo' => null,
            'dt_resultado' => null,
            'foi_executado_sem_baixa' => 0,
            'exame_coletado_em_prescricao_mais_nova' => 0,
            'prescricao_mais_nova_pendente_info' => null,
        ];

        $result = $method->invoke($repository, $base);

        $this->assertFalse($result['foi_executado_sem_baixa']);
        $this->assertFalse($result['exame_coletado_em_prescricao_mais_nova']);
        $this->assertNull($result['prescricao_mais_nova_pendente_info']);
        $this->assertSame('Hematologia', $result['classificacao']);

        // Flag: executado sem baixa
        $rowFlag = clone $base;
        $rowFlag->foi_executado_sem_baixa = 1;
        $resultFlag = $method->invoke($repository, $rowFlag);
        $this->assertTrue($resultFlag['foi_executado_sem_baixa']);

        // Flag: exame coletado em prescrição mais nova
        $rowNova = clone $base;
        $rowNova->exame_coletado_em_prescricao_mais_nova = 1;
        $rowNova->prescricao_mais_nova_pendente_info = '12999 — 10/04/2026';
        $resultNova = $method->invoke($repository, $rowNova);
        $this->assertTrue($resultNova['exame_coletado_em_prescricao_mais_nova']);
        $this->assertSame('12999 — 10/04/2026', $resultNova['prescricao_mais_nova_pendente_info']);
    }
}
