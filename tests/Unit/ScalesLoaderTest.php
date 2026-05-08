<?php

namespace Tests\Unit;

use App\Repositories\EMR\PatientScalesRepository;
use App\Services\PatientData\Loaders\ScalesLoader;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScalesLoaderTest extends TestCase
{
    #[Test]
    public function it_does_not_cache_failed_scale_fetches(): void
    {
        $repository = Mockery::mock(PatientScalesRepository::class);
        $repository->shouldReceive('getPatientsScalesUnified')
            ->once()
            ->with([22787001])
            ->andThrow(new \RuntimeException('boom'));

        Cache::shouldReceive('get')
            ->once()
            ->with('sector_scales_1147')
            ->andReturnNull();
        Cache::shouldNotReceive('put');

        $loader = new ScalesLoader($repository);

        $result = $loader->load(1147, [22787001]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_caches_successful_scale_fetches(): void
    {
        $repository = Mockery::mock(PatientScalesRepository::class);
        $repository->shouldReceive('getPatientsScalesUnified')
            ->once()
            ->with([22787001])
            ->andReturn([
                22787001 => [
                    'mews' => [
                        'score' => 5,
                        'previous_score' => 3,
                        'previous_timestamp' => '04/05/2026 06:00',
                        'needs_assessment' => false,
                        'increased' => true,
                        'classification' => 'Crítico',
                        'styling' => ['bg' => 'bg-red-100', 'border' => 'border-red-300', 'text' => 'text-red-700'],
                        'shift' => 'manhã',
                        'timestamp' => '04/05/2026 10:00',
                    ],
                ],
            ]);

        Cache::shouldReceive('get')
            ->once()
            ->with('sector_scales_1147')
            ->andReturnNull();
        Cache::shouldReceive('put')
            ->once()
            ->with('sector_scales_1147', Mockery::on(function (array $value): bool {
                return isset($value[22787001]['mews_score'])
                    && $value[22787001]['mews_score'] === 5
                    && $value[22787001]['ews_display']['label'] === 'MEWS';
            }), 600)
            ->andReturnTrue();

        $loader = new ScalesLoader($repository);

        $result = $loader->load(1147, [22787001]);

        $this->assertSame(5, $result[22787001]['mews_score']);
        $this->assertSame('MEWS', $result[22787001]['ews_display']['label']);
    }
}
