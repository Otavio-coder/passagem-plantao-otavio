<?php

namespace Tests\Unit;

use App\Repositories\EMR\PatientPrescricoesRepository;
use App\Repositories\EMR\PatientTherapeuticPlanRepository;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrescriptionActivePlanFilterTest extends TestCase
{
    #[Test]
    public function nutrition_query_limits_null_end_date_to_recent_releases(): void
    {
        $repository = new PatientPrescricoesRepository;

        $connection = Mockery::mock();
        DB::shouldReceive('connection')->once()->with('tasy')->andReturn($connection);

        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertSame(['attendance' => 1001], $bindings);
                $this->assertStringContainsString('cd.DT_FIM >= SYSDATE OR (cd.DT_FIM IS NULL AND cd.DT_LIBERACAO >= TRUNC(SYSDATE) - 1)', $sql);

                return true;
            })
            ->andReturn([]);

        $result = $repository->getNutricao(1001);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_count', $result);
    }

    #[Test]
    public function recommendations_query_limits_null_end_date_to_recent_releases(): void
    {
        $repository = new PatientPrescricoesRepository;

        $connection = Mockery::mock();
        DB::shouldReceive('connection')->once()->with('tasy')->andReturn($connection);

        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                $this->assertSame(['attendance' => 1002], $bindings);
                $this->assertStringContainsString('cr.DT_FIM >= SYSDATE OR (cr.DT_FIM IS NULL AND cr.DT_LIBERACAO >= TRUNC(SYSDATE) - 1)', $sql);

                return true;
            })
            ->andReturn([]);

        $result = $repository->getRecomendacoes(1002);

        $this->assertSame(0, $result['total_count']);
    }

    #[Test]
    public function therapeutic_orders_query_applies_recent_release_guard_in_main_and_dedup_filters(): void
    {
        $repository = new PatientTherapeuticPlanRepository;

        $method = new \ReflectionMethod($repository, 'ordersQuery');
        $method->setAccessible(true);

        $sql = (string) $method->invoke($repository);

        $this->assertStringContainsString(
            'cr.DT_FIM >= SYSDATE OR (cr.DT_FIM IS NULL AND cr.DT_LIBERACAO >= TRUNC(SYSDATE) - 1)',
            $sql
        );

        $this->assertStringContainsString(
            'cr2.DT_FIM >= SYSDATE OR (cr2.DT_FIM IS NULL AND cr2.DT_LIBERACAO >= TRUNC(SYSDATE) - 1)',
            $sql
        );
    }

    #[Test]
    public function therapeutic_gasotherapy_and_dialysis_queries_limit_null_end_date_to_recent_releases(): void
    {
        $repository = new PatientTherapeuticPlanRepository;

        $gasotherapyMethod = new \ReflectionMethod($repository, 'gasotherapyQuery');
        $gasotherapyMethod->setAccessible(true);
        $gasotherapySql = (string) $gasotherapyMethod->invoke($repository);

        $this->assertStringContainsString(
            'cg.DT_FIM >= SYSDATE OR (cg.DT_FIM IS NULL AND cg.DT_LIBERACAO >= TRUNC(SYSDATE) - 1)',
            $gasotherapySql
        );

        $dialysisMethod = new \ReflectionMethod($repository, 'dialysisQuery');
        $dialysisMethod->setAccessible(true);
        $dialysisSql = (string) $dialysisMethod->invoke($repository);

        $this->assertStringContainsString(
            'cd.DT_FIM >= SYSDATE OR (cd.DT_FIM IS NULL AND cd.DT_LIBERACAO >= TRUNC(SYSDATE) - 1)',
            $dialysisSql
        );
    }
}
