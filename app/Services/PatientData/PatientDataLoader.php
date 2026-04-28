<?php

namespace App\Services\PatientData;

use App\Repositories\EMR\PatientClinicalRepository;
use App\Repositories\EMR\PatientMultidisciplinaryRepository;
use App\Repositories\EMR\PatientScalesRepository;
use App\Repositories\EMR\PatientSurgeryRepository;
use App\Services\PatientData\Contracts\SectorLoader;
use App\Services\PatientData\Loaders\ClinicalLoader;
use App\Services\PatientData\Loaders\DemographicsLoader;
use App\Services\PatientData\Loaders\MultidisciplinaryLoader;
use App\Services\PatientData\Loaders\PendingEventsLoader;
use App\Services\PatientData\Loaders\ScalesLoader;
use App\Services\PatientData\Loaders\SurgeryLoader;
use App\Services\PendingEvents\PatientPendingEventsService;
use App\Services\Tasy\TasyFormatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Fork\Fork;

class PatientDataLoader
{
    private int $sectorId;

    /** @var string[] */
    private array $requested = ['demographics'];

    /** @var array<string, SectorLoader> */
    private array $loaders;

    private function __construct(int $sectorId)
    {
        $this->sectorId = $sectorId;

        $this->loaders = [
            'demographics' => new DemographicsLoader,
            'scales' => new ScalesLoader(app(PatientScalesRepository::class)),
            'pending_events' => new PendingEventsLoader(app(PatientPendingEventsService::class)),
            'clinical' => new ClinicalLoader(app(PatientClinicalRepository::class)),
            'multidisciplinary' => new MultidisciplinaryLoader(app(PatientMultidisciplinaryRepository::class)),
            'surgery' => new SurgeryLoader(app(PatientSurgeryRepository::class)),
        ];
    }

    public static function forSector(int $sectorId): static
    {
        return new static($sectorId);
    }

    public function include(string ...$keys): static
    {
        $this->requested = array_unique(['demographics', ...$keys]);

        return $this;
    }

    /**
     * Execute loaders and return a flat array of patient arrays.
     *
     * Demographics always runs first (determines the attendance list).
     * All other requested loaders run in parallel via pcntl_fork when available.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $demographics = $this->loaders['demographics']->load($this->sectorId, []);

        $attendanceNumbers = array_values(array_filter(
            array_keys($demographics),
            fn ($nr) => is_int($nr) && $nr > 0 && ($demographics[$nr]['has_patient'] ?? false)
        ));

        $otherKeys = array_values(array_filter(
            $this->requested,
            fn ($k) => $k !== 'demographics' && isset($this->loaders[$k])
        ));

        $loaded = $this->runLoaders($otherKeys, $attendanceNumbers);

        $applyScaleStyling = in_array('scales', $this->requested, true);
        $formatter = $applyScaleStyling ? new TasyFormatter : null;

        $result = [];

        foreach ($demographics as $nr => $patient) {
            foreach ($loaded as $data) {
                if (isset($data[$nr])) {
                    $patient = array_merge($patient, $data[$nr]);
                }
            }

            if ($applyScaleStyling && $formatter) {
                $patient = $formatter->applyCardStyling($patient, $patient['is_pediatric'] ?? false);
            }

            $result[] = $patient;
        }

        return $result;
    }

    public function clearCache(): void
    {
        $keys = [
            "sector_demographics_{$this->sectorId}",
            "sector_scales_{$this->sectorId}",
            "sector_clinical_{$this->sectorId}",
            "sector_multi_{$this->sectorId}",
            "sector_surgery_{$this->sectorId}",
            "sector_pending_fast_{$this->sectorId}",
            "sector_handover_{$this->sectorId}_manha",
            "sector_handover_{$this->sectorId}_tarde",
            "sector_handover_{$this->sectorId}_noite",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Run loaders in parallel via pcntl_fork when available, sequentially otherwise.
     * Each fork reconnects DB/Redis after forking to avoid shared connection handles.
     *
     * @param  string[]  $keys
     * @return array<int, array<int, mixed>> Indexed array of per-loader results
     */
    private function runLoaders(array $keys, array $attendanceNumbers): array
    {
        if (empty($keys)) {
            return [];
        }

        if (! function_exists('pcntl_fork') || count($keys) < 2 || app()->environment('testing')) {
            return array_map(
                fn ($key) => $this->loaders[$key]->load($this->sectorId, $attendanceNumbers),
                $keys
            );
        }

        $sectorId = $this->sectorId;
        $loaders = $this->loaders;

        try {
            return Fork::new()
                ->after(child: function () {
                    DB::connection('tasy')->reconnect();
                    DB::connection('mysql')->reconnect();
                    try {
                        app('redis')->connection('default')->client()->close();
                    } catch (\Throwable) {
                    }
                })
                ->run(...array_map(
                    fn ($key) => fn () => $loaders[$key]->load($sectorId, $attendanceNumbers),
                    $keys
                ));
        } catch (\Throwable $e) {
            Log::warning('PatientDataLoader: Fork failed, falling back to sequential', [
                'error' => $e->getMessage(),
            ]);

            return array_map(
                fn ($key) => $loaders[$key]->load($sectorId, $attendanceNumbers),
                $keys
            );
        }
    }
}
