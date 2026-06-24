<?php

namespace Tests\Unit;

use App\Repositories\EMR\PatientPrescriptionsRepository;
use App\Services\Tasy\PrescriptionFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrescriptionActivePlanFilterTest extends TestCase
{
    #[Test]
    public function therapeutic_orders_query_applies_recent_release_guard_in_main_and_dedup_filters(): void
    {
        $repository = new PatientPrescriptionsRepository(new PrescriptionFormatter);

        $method = new \ReflectionMethod($repository, 'recommendationsQuery');
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
        $repository = new PatientPrescriptionsRepository(new PrescriptionFormatter);

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
