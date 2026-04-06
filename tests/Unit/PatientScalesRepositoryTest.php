<?php

namespace Tests\Unit;

use App\Repositories\EMR\PatientScalesRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PatientScalesRepositoryTest extends TestCase
{
    #[Test]
    public function it_fetches_all_scales_with_a_single_union_query_per_chunk(): void
    {
        $repository = new PatientScalesRepository;

        $attendanceList = [1001, 1002];

        $connection = DB::shouldReceive('connection')
            ->once()
            ->with('tasy')
            ->andReturnSelf()
            ->getMock();

        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings) use ($attendanceList): bool {
                $expectedBindings = array_merge(
                    $attendanceList,
                    $attendanceList,
                    $attendanceList,
                    $attendanceList,
                    $attendanceList,
                    $attendanceList
                );

                return str_contains($sql, 'UNION ALL')
                    && str_contains($sql, 'ROW_NUMBER() OVER (PARTITION BY nr_atendimento')
                    && str_contains($sql, 'WHERE rn <= 2')
                    && $bindings === $expectedBindings;
            })
            ->andReturn([
                (object) [
                    'scale_type' => 'mews',
                    'nr_atendimento' => 1001,
                    'score_value' => 5,
                    'dt_ref' => '2026-04-06 10:00:00',
                    'rn' => 1,
                ],
                (object) [
                    'scale_type' => 'mews',
                    'nr_atendimento' => 1001,
                    'score_value' => 3,
                    'dt_ref' => '2026-04-06 04:00:00',
                    'rn' => 2,
                ],
                (object) [
                    'scale_type' => 'pain',
                    'nr_atendimento' => 1002,
                    'score_value' => 8,
                    'dt_ref' => '2026-04-06 09:00:00',
                    'rn' => 1,
                ],
            ]);

        $result = $repository->getPatientsScalesUnified($attendanceList);

        $this->assertArrayHasKey(1001, $result);
        $this->assertArrayHasKey(1002, $result);

        $this->assertSame(5, $result[1001]['mews']['score']);
        $this->assertSame(3, $result[1001]['mews']['previous_score']);
        $this->assertTrue($result[1001]['mews']['increased']);

        $this->assertSame(8, $result[1002]['pain']['score']);
        $this->assertSame('turno', $result[1002]['pain']['period']);

        $this->assertArrayHasKey('vte', $result[1001]);
        $this->assertArrayHasKey('braden', $result[1001]);
        $this->assertArrayHasKey('morse', $result[1001]);
        $this->assertArrayHasKey('pews', $result[1001]);
    }
}
