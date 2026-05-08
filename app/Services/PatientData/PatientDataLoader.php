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
use Illuminate\Support\Facades\Log;

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
     * Execute loaders sequentially and return a flat array of patient arrays.
     * Demographics always runs first (determines the attendance list).
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $totalStart = hrtime(true);
        $timings = [];

        $t = hrtime(true);
        $demographics = $this->loaders['demographics']->load($this->sectorId, []);
        $timings['demographics'] = round((hrtime(true) - $t) / 1e6);

        $attendanceNumbers = array_values(array_filter(
            array_keys($demographics),
            fn ($nr) => is_int($nr) && $nr > 0 && ($demographics[$nr]['has_patient'] ?? false)
        ));

        $otherKeys = array_values(array_filter(
            $this->requested,
            fn ($k) => $k !== 'demographics' && isset($this->loaders[$k])
        ));

        $loaded = $this->runLoaders($otherKeys, $attendanceNumbers, $timings);

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

        $timings['total'] = round((hrtime(true) - $totalStart) / 1e6);
        $timings['patient_count'] = count(array_filter($result, fn ($p) => $p['has_patient'] ?? false));

        Log::channel('daily')->debug('PatientDataLoader.get timings (ms)', [
            'sector_id' => $this->sectorId,
            ...$timings,
        ]);

        return $result;
    }

    /**
     * Clears only the dynamic caches (pending events + handover status).
     * Demographics, scales, clinical, multidisciplinary and surgery data
     * change rarely within a session and keep their TTL-based expiry.
     * Use this for the "Atualizar" button to avoid triggering a full cold load.
     */
    public function clearDynamicCache(): void
    {
        $keys = [
            "sector_pending_fast_{$this->sectorId}",
            "sector_handover_{$this->sectorId}_M",
            "sector_handover_{$this->sectorId}_T",
            "sector_handover_{$this->sectorId}_N",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clears all caches for the sector (full cold reload).
     * Only use for explicit "force refresh" scenarios.
     */
    public function clearCache(): void
    {
        $keys = [
            "sector_demographics_{$this->sectorId}",
            "sector_scales_{$this->sectorId}",
            "sector_clinical_{$this->sectorId}",
            "sector_multi_{$this->sectorId}",
            "sector_surgery_{$this->sectorId}",
            "sector_pending_fast_{$this->sectorId}",
            "sector_handover_{$this->sectorId}_M",
            "sector_handover_{$this->sectorId}_T",
            "sector_handover_{$this->sectorId}_N",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * @param  string[]  $keys
     * @param  array<string, int>  $timings
     * @return array<int, array<int, mixed>>
     */
    private function runLoaders(array $keys, array $attendanceNumbers, array &$timings = []): array
    {
        if (empty($keys)) {
            return [];
        }

        return array_map(function ($key) use ($attendanceNumbers, &$timings) {
            $t = hrtime(true);
            $result = $this->loaders[$key]->load($this->sectorId, $attendanceNumbers);
            $timings[$key] = round((hrtime(true) - $t) / 1e6);

            return $result;
        }, $keys);
    }
}
