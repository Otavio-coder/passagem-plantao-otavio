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

/**
 * Orquestrador de carregamento de dados de pacientes por setor.
 *
 * Cada categoria de dado (escalas, eventos pendentes, clínico, etc.) é implementada como um
 * SectorLoader independente com seu próprio cache. Isso permite:
 * - Invalidar apenas as caches dinâmicas (eventos pendentes, passagem) sem recarregar Oracle.
 * - Adicionar ou remover categorias sem alterar o contrato do chamador.
 * - Medir o tempo de cada loader separadamente para identificar gargalos.
 *
 * Fluxo: DemographicsLoader roda primeiro e define a lista de nr_atendimento.
 * Os demais loaders recebem essa lista e buscam dados apenas para os pacientes presentes.
 */
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
     * Executa os loaders em sequência e retorna um array plano de dados de paciente.
     *
     * Demographics obrigatoriamente roda primeiro: ele retorna o mapa de leitos do setor,
     * que determina quais nr_atendimento existem. Os loaders subsequentes recebem essa lista
     * e fazem queries Oracle em batch, evitando N round-trips individuais.
     *
     * Os tempos de cada loader são registrados em debug para diagnóstico de lentidão.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $t0 = hrtime(true);
        $demographics = $this->loaders['demographics']->load($this->sectorId, []);
        $tDemo = round((hrtime(true) - $t0) / 1e6);

        $attendanceNumbers = array_values(array_filter(
            array_keys($demographics),
            fn ($nr) => is_int($nr) && $nr > 0 && ($demographics[$nr]['has_patient'] ?? false)
        ));

        $otherKeys = array_values(array_filter(
            $this->requested,
            fn ($k) => $k !== 'demographics' && isset($this->loaders[$k])
        ));

        $t1 = hrtime(true);
        $loaded = $this->runLoaders($otherKeys, $attendanceNumbers);
        $tLoaders = round((hrtime(true) - $t1) / 1e6);

        $tTotal = round((hrtime(true) - $t0) / 1e6);
        Log::warning('[PatientDataLoader] sector='.$this->sectorId.' patients='.count($attendanceNumbers).' demo='.$tDemo.'ms loaders='.$tLoaders.'ms total='.$tTotal.'ms keys='.implode(',', $otherKeys));

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

    /**
     * Limpa caches dinâmicos: eventos pendentes, passagem de plantão e escalas.
     * Escalas são aferidas por turno e mudam com frequência — devem ser recarregadas
     * no "Atualizar". Demographics, clínico, multidisciplinar e cirurgia são estáveis
     * durante a sessão e expiram pelo TTL sem necessidade de invalidação manual.
     */
    public function clearDynamicCache(): void
    {
        $keys = [
            "sector_pending_fast_{$this->sectorId}",
            "sector_scales_{$this->sectorId}",
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
     * Runs non-demographics loaders sequentially, logging each loader's duration.
     * Parallel execution via pcntl_fork is not safe inside HTTP request handlers
     * (corrupts FastCGI connection state). Cache warming via the artisan command
     * is the correct approach for reducing cold-load time.
     *
     * @param  string[]  $keys
     * @return array<int, array<int, mixed>>
     */
    private function runLoaders(array $keys, array $attendanceNumbers): array
    {
        if (empty($keys)) {
            return [];
        }

        $results = [];

        foreach ($keys as $key) {
            $t = hrtime(true);
            $results[] = $this->loaders[$key]->load($this->sectorId, $attendanceNumbers);
            Log::warning('[PatientDataLoader] key='.$key.' ms='.round((hrtime(true) - $t) / 1e6));
        }

        return $results;
    }
}
