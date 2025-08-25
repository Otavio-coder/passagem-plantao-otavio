<?php
// filepath: /var/www/passagem-plantao/app/Livewire/SbarReport.php

namespace App\Livewire;

use App\Models\EMR\Patient;
use App\Models\EMR\BedUnit;
use App\Models\EMR\Sector;
use App\Models\EMR\Hospital;
use App\Models\System\SystemConfiguration;
use Livewire\Component;
use Livewire\Attributes\Lazy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

#[Lazy]
class SbarReport extends Component
{
    public $loading = true;
    public $loadingMessage = 'Carregando dados...';
    public $errorMessage = null;
    public $lastRefresh = null;

    public $patients = [];
    public $sectors = [];
    public $allSectors = [];
    public $hospitals = [];
    public $beds = [];
    
    public $selectedHospital = null;
    public $selectedSector = null;
    public $currentHospitalName = 'Carregando...';

    // Filtros
    public $mewsFilter = 'all';
    public $surgicalFilter = 'all';
    public $orderBy = 'leito';
    public $orderDirection = 'asc';

    // Cache otimizado
    private $structuralCacheKey = 'sbar_structural_v9';
    private $patientsCacheTime = 1200; // 20 minutos
    private $structuralCacheTime = 14400; // 4 horas

    // Performance tracking
    private $startTime;

    public function mount()
    {
        $this->startTime = microtime(true);
        
        try {
            $this->loading = true;
            $this->loadingMessage = 'Inicializando sistema SBAR...';
            
            // Carrega dados estruturais com cache agressivo
            if (!$this->loadStructuralDataOptimized()) {
                $this->handleNoDataError();
                return;
            }

            // Seleções iniciais otimizadas
            $this->setInitialSelectionsOptimized();
            
            // Carrega pacientes apenas se tiver setor válido
            if ($this->selectedSector) {
                $this->loadPatientsWithOptimizations();
            } else {
                $this->handleNoSectorError();
            }
            
            $this->logPerformance('mount');
            
        } catch (\Exception $e) {
            $this->handleMountError($e);
        }
    }

    public function placeholder()
    {
        return view('livewire.sbar-report-placeholder');
    }

    /**
     * ✅ ULTRA-OTIMIZADO: Carregamento estrutural com múltiplas camadas de cache
     */
    private function loadStructuralDataOptimized(): bool
    {
        $structural = Cache::remember($this->structuralCacheKey, $this->structuralCacheTime, function() {
            $startTime = microtime(true);
            
            // Cache de configurações para evitar múltiplas consultas
            $config = Cache::remember('sbar_config_v3', 3600, function() {
                return [
                    'hospitals' => SystemConfiguration::allowedHospitalCodes(),
                    'sectors' => SystemConfiguration::allowedSectorCodes(),
                    'beds' => SystemConfiguration::allowedBedCodes()
                ];
            });

            if (empty($config['hospitals'])) {
                return ['hospitals' => [], 'sectors' => [], 'beds' => []];
            }

            // Query única otimizada para hospitais
            $hospitals = Hospital::whereIn('nr_sequencia', $config['hospitals'])
                ->select('nr_sequencia as hospital_id', 'ds_agrupamento as hospital_name')
                ->orderBy('ds_agrupamento')
                ->get()
                ->toArray();

            if (empty($hospitals)) {
                return ['hospitals' => [], 'sectors' => [], 'beds' => []];
            }

            // Query otimizada para setores com join implícito
            $sectors = Sector::whereIn('cd_setor_atendimento', $config['sectors'])
                ->whereIn('nr_seq_agrupamento', $config['hospitals'])
                ->select('cd_setor_atendimento', 'ds_setor_atendimento', 'nr_seq_agrupamento as hospital_id')
                ->orderBy('ds_setor_atendimento')
                ->get()
                ->toArray();

            // Pré-calcula leitos permitidos para evitar processamento no runtime
            $beds = $this->preprocessAllowedBeds($config['beds'], array_column($sectors, 'cd_setor_atendimento'));

            $loadTime = round((microtime(true) - $startTime) * 1000);
            Log::info("SBAR: Structural data loaded in {$loadTime}ms - " . 
                     count($hospitals) . " hospitals, " . 
                     count($sectors) . " sectors, " . 
                     count($beds) . " beds");

            return compact('hospitals', 'sectors', 'beds');
        });

        $this->hospitals = $structural['hospitals'];
        $this->allSectors = $structural['sectors'];
        $this->beds = $structural['beds'];

        return !empty($this->hospitals);
    }

    /**
     * ✅ NOVO: Pré-processa leitos permitidos
     */
    private function preprocessAllowedBeds(array $allowedBeds, array $sectorIds): array
    {
        if (empty($sectorIds) || empty($allowedBeds)) {
            return [];
        }

        $beds = BedUnit::whereIn('cd_setor_atendimento', $sectorIds)
            ->select('cd_unidade_basica', 'cd_setor_atendimento')
            ->get();

        $allowedBedsSet = array_flip($allowedBeds);
        
        return $beds->filter(function($bed) use ($allowedBedsSet) {
            $key = $bed->cd_unidade_basica . '|' . $bed->cd_setor_atendimento;
            return isset($allowedBedsSet[$key]);
        })->map(function($bed) {
            return [
                'cd_unidade_basica' => $bed->cd_unidade_basica,
                'cd_setor_atendimento' => $bed->cd_setor_atendimento
            ];
        })->values()->toArray();
    }

    /**
     * ✅ OTIMIZADO: Seleções iniciais mais eficientes
     */
    private function setInitialSelectionsOptimized()
    {
        // Seleciona primeiro hospital disponível
        $this->selectedHospital = $this->hospitals[0]['hospital_id'] ?? null;
        
        // Filtra setores do hospital selecionado
        $this->updateSectorsForHospitalOptimized();
        
        // Seleciona primeiro setor disponível
        $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'] ?? null;
        
        // Atualiza nome do hospital
        $this->updateHospitalNameOptimized();
    }

    /**
     * ✅ ULTRA-OTIMIZADO: Carregamento de pacientes com cache inteligente por hora
     */
    public function loadPatientsWithOptimizations()
    {
        try {
            $this->loading = true;
            $this->loadingMessage = "Carregando pacientes do setor...";

            $cacheKey = $this->generatePatientCacheKey();
            
            $allPatients = Cache::remember($cacheKey, $this->patientsCacheTime, function() {
                return $this->fetchAndProcessPatients();
            });
            
            $this->applyFiltersOptimized($allPatients);
            $this->lastRefresh = now()->format('H:i:s');

            $this->logPerformance('loadPatients', count($this->patients));

        } catch (\Exception $e) {
            $this->handlePatientLoadError($e);
        } finally {
            $this->loading = false;
            $this->loadingMessage = '';
        }
    }

    /**
     * ✅ NOVO: Gera chave de cache mais específica
     */
    private function generatePatientCacheKey(): string
    {
        $hour = date('Y-m-d_H');
        return "sbar_patients_{$this->selectedSector}_{$hour}_v2";
    }

    /**
     * ✅ OTIMIZADO: Busca e processa pacientes
     */
    private function fetchAndProcessPatients(): array
    {
        $startTime = microtime(true);
        
        $patientModel = new Patient();
        $patients = $patientModel->getAllPatientsInSector($this->selectedSector);
        
        // Processamento em lote para melhor performance
        $processedPatients = $this->batchProcessPatients($patients);
        
        $loadTime = round((microtime(true) - $startTime) * 1000);
        Log::info("SBAR: {$patients->count()} patients processed in {$loadTime}ms");
        
        return $processedPatients;
    }

    /**
     * ✅ NOVO: Processamento em lote de pacientes
     */
    private function batchProcessPatients($patients): array
    {
        $processed = [];
        $today = Carbon::today();
        
        foreach ($patients as $patient) {
            $data = is_array($patient) ? $patient : (array) $patient;
            
            // Cálculos otimizados
            $internmentDays = $data['internment_days'] ?? null;
            $isNewPatient = $internmentDays !== null && $internmentDays >= 0 && $internmentDays < 1;
            $mewsScore = $data['mews_score'] ?? null;
            
            // Classes CSS pré-calculadas
            [$gradientClass, $borderClass, $textColorClass] = $this->calculatePatientClasses($isNewPatient, $mewsScore);
            
            // Adiciona dados processados
            $data['is_new_patient'] = $isNewPatient;
            $data['gradient_class'] = $gradientClass;
            $data['border_class'] = $borderClass;
            $data['text_color_class'] = $textColorClass;
            $data['mews_display'] = $mewsScore !== null ? "MEWS: {$mewsScore}" : 'MEWS: N/A';
            
            $processed[] = $data;
        }
        
        return $processed;
    }

    /**
     * ✅ NOVO: Calcula classes CSS do paciente
     */
    private function calculatePatientClasses(bool $isNewPatient, ?int $mewsScore): array
    {
        if ($isNewPatient) {
            return [
                'from-green-50 to-green-100',
                'border-2 border-green-400',
                'text-green-800'
            ];
        }
        
        if ($mewsScore !== null) {
            if ($mewsScore >= 5) {
                return [
                    'from-red-50 to-red-100',
                    'border-2 border-red-500',
                    'text-red-800'
                ];
            } elseif ($mewsScore >= 3) {
                return [
                    'from-amber-50 to-amber-100',
                    'border-2 border-amber-500',
                    'text-amber-800'
                ];
            }
        }
        
        return [
            'from-blue-50 to-blue-100',
            'border border-gray-200',
            'text-sky-800'
        ];
    }

    /**
     * ✅ ULTRA-OTIMIZADO: Filtros com array indexado e algoritmos eficientes
     */
    private function applyFiltersOptimized(array $allPatients = [])
    {
        if (empty($allPatients)) {
            $this->patients = [];
            return;
        }

        $filtered = $allPatients;

        // Filtro MEWS com short-circuit evaluation
        if ($this->mewsFilter !== 'all') {
            $filtered = array_filter($filtered, $this->getMewsFilterCallback());
        }

        // Filtro cirurgia
        if ($this->surgicalFilter === 'with_surgery') {
            $filtered = array_filter($filtered, function($p) {
                return ($p['has_patient'] ?? false) && ($p['has_surgery'] ?? false);
            });
        }

        // Ordenação otimizada com algoritmo estável
        if (!empty($filtered)) {
            usort($filtered, [$this, 'comparePatients']);
        }

        $this->patients = array_values($filtered);
    }

    /**
     * ✅ NOVO: Callback otimizado para filtro MEWS
     */
    private function getMewsFilterCallback(): \Closure
    {
        return function($p) {
            if (!($p['has_patient'] ?? false)) return false;
            
            $score = $p['mews_score'] ?? null;
            
            return match($this->mewsFilter) {
                'critical' => $score !== null && $score >= 5,
                'warning' => $score !== null && $score >= 3 && $score < 5,
                'normal' => $score === null || $score < 3,
                default => true
            };
        };
    }

    /**
     * ✅ OTIMIZADO: Função de comparação com cache de resultado
     */
    private function comparePatients($a, $b): int
    {
        $dir = $this->orderDirection === 'asc' ? 1 : -1;
        
        $result = match($this->orderBy) {
            'leito' => strcmp($a['cd_unidade_basica'] ?? '', $b['cd_unidade_basica'] ?? ''),
            'mews' => ($b['mews_score'] ?? 0) <=> ($a['mews_score'] ?? 0),
            'name' => strcmp($a['nm_pessoa_fisica'] ?? '', $b['nm_pessoa_fisica'] ?? ''),
            'prontuario' => strcmp($a['nr_prontuario'] ?? '', $b['nr_prontuario'] ?? ''),
            'internment' => ($a['internment_days'] ?? 0) <=> ($b['internment_days'] ?? 0),
            'age' => ($a['age'] ?? 0) <=> ($b['age'] ?? 0),
            default => 0
        };
        
        return $dir * $result;
    }

    /**
     * ✅ OTIMIZADO: Métodos de filtro sem recarregamento
     */
    public function applyMewsFilter($filter)
    {
        $this->mewsFilter = $filter;
        $this->applyCurrentFiltersOptimized();
    }

    public function applySurgicalFilter($filter)
    {
        $this->surgicalFilter = $filter;
        $this->applyCurrentFiltersOptimized();
    }

    public function applyOrderBy($field)
    {
        $this->orderBy = $field;
        $this->applyCurrentFiltersOptimized();
    }

    public function toggleOrderDirection()
    {
        $this->orderDirection = $this->orderDirection === 'asc' ? 'desc' : 'asc';
        $this->applyCurrentFiltersOptimized();
    }

    /**
     * ✅ NOVO: Aplica filtros sem recarregar dados do cache
     */
    private function applyCurrentFiltersOptimized()
    {
        $cacheKey = $this->generatePatientCacheKey();
        $allPatients = Cache::get($cacheKey, []);
        $this->applyFiltersOptimized($allPatients);
    }

    /**
     * ✅ AUTO-REFRESH otimizado para dashboard
     */
    public function autoRefresh()
    {
        $this->clearPatientCacheOptimized();
        $this->loadPatientsWithOptimizations();
        $this->dispatch('auto-refreshed');
        
        Log::info('SBAR: Auto-refresh executado via dashboard');
    }

    /**
     * ✅ REFRESH manual otimizado
     */
    public function refreshData()
    {
        $this->loading = true;
        $this->loadingMessage = "Atualizando dados...";
        $this->errorMessage = null;

        $this->clearPatientCacheOptimized();
        $this->loadPatientsWithOptimizations();
        
        Log::info('SBAR: Refresh manual executado');
    }

    /**
     * ✅ NOVO: Limpa cache de forma otimizada
     */
    private function clearPatientCacheOptimized()
    {
        $cacheKey = $this->generatePatientCacheKey();
        Cache::forget($cacheKey);
        
        // Limpa também cache da hora anterior para evitar dados obsoletos
        $previousHour = date('Y-m-d_H', strtotime('-1 hour'));
        $previousCacheKey = "sbar_patients_{$this->selectedSector}_{$previousHour}_v2";
        Cache::forget($previousCacheKey);
    }

    public function resetFilters()
    {
        $this->mewsFilter = 'all';
        $this->surgicalFilter = 'all';
        $this->orderBy = 'leito';
        $this->orderDirection = 'asc';

        $this->applyCurrentFiltersOptimized();
        session()->flash('message', 'Filtros resetados com sucesso!');
    }

    /**
     * ✅ MUDANÇA DE HOSPITAL otimizada
     */
    public function changeHospital($hospitalId)
    {
        $this->loading = true;
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;

        $this->updateSectorsForHospitalOptimized();
        $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'] ?? null;
        $this->updateHospitalNameOptimized();

        $this->patients = [];

        if ($this->selectedSector) {
            $this->loadPatientsWithOptimizations();
        } else {
            $this->loading = false;
        }
    }

    /**
     * ✅ MUDANÇA DE SETOR otimizada
     */
    public function changeSelector($sectorId)
    {
        // Validação rápida
        $validSector = null;
        foreach ($this->sectors as $sector) {
            if ($sector['cd_setor_atendimento'] == $sectorId) {
                $validSector = $sector;
                break;
            }
        }

        if (!$validSector) {
            $this->errorMessage = "Setor não válido para o hospital selecionado.";
            return;
        }

        $this->loading = true;
        $this->selectedSector = $sectorId;
        $this->patients = [];
        $this->updateHospitalNameOptimized();
        $this->loadPatientsWithOptimizations();
    }

    /**
     * ✅ ATUALIZAÇÃO otimizada do nome do hospital
     */
    private function updateHospitalNameOptimized()
    {
        // Busca rápida com early return
        foreach ($this->allSectors as $sector) {
            if ($sector['cd_setor_atendimento'] == $this->selectedSector) {
                foreach ($this->hospitals as $hospital) {
                    if ($hospital['hospital_id'] == $sector['hospital_id']) {
                        $this->currentHospitalName = $hospital['hospital_name'];
                        return;
                    }
                }
                break;
            }
        }
        
        $this->currentHospitalName = 'Hospital Não Identificado';
    }

    /**
     * ✅ ATUALIZAÇÃO otimizada de setores
     */
    private function updateSectorsForHospitalOptimized()
    {
        if (!$this->selectedHospital || empty($this->allSectors)) {
            $this->sectors = [];
            return;
        }

        $this->sectors = [];
        foreach ($this->allSectors as $sector) {
            if ($sector['hospital_id'] == $this->selectedHospital) {
                $this->sectors[] = $sector;
            }
        }
    }

    /**
     * ✅ ERROR HANDLERS otimizados
     */
    private function handleNoDataError()
    {
        $this->loading = false;
        $this->errorMessage = "Nenhum hospital configurado. Solicite ao gestor a configuração pelo painel administrativo.";
        Log::warning("SBAR: Nenhum hospital permitido encontrado");
    }

    private function handleNoSectorError()
    {
        $this->loading = false;
        $this->errorMessage = "Nenhum setor válido encontrado para o hospital selecionado.";
        Log::warning("SBAR: Nenhum setor válido - Hospital: {$this->selectedHospital}");
    }

    private function handleMountError(\Exception $e)
    {
        $this->loading = false;
        $this->errorMessage = "Erro durante inicialização: " . $e->getMessage();
        Log::error("SBAR mount error: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
    }

    private function handlePatientLoadError(\Exception $e)
    {
        $this->patients = [];
        $this->errorMessage = "Erro ao carregar pacientes: " . $e->getMessage();
        Log::error("SBAR patient load error: " . $e->getMessage(), [
            'sector' => $this->selectedSector,
            'hospital' => $this->selectedHospital
        ]);
    }

    /**
     * ✅ PERFORMANCE LOGGING
     */
    private function logPerformance(string $operation, int $count = 0)
    {
        if (!$this->startTime) return;
        
        $duration = round((microtime(true) - $this->startTime) * 1000);
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
        Log::info("SBAR Performance - {$operation}: {$duration}ms, {$memory}MB, {$count} items");
    }

    public function render()
    {
        return view('livewire.sbar-report', [
            'patients' => $this->patients,
            'hospitals' => $this->hospitals,
            'sectors' => $this->sectors,
            'beds' => $this->beds,
            'errorMessage' => $this->errorMessage,
            'loadingMessage' => $this->loadingMessage,
            'mewsFilter' => $this->mewsFilter,
            'surgicalFilter' => $this->surgicalFilter,
            'orderBy' => $this->orderBy,
            'orderDirection' => $this->orderDirection,
            'selectedHospital' => $this->selectedHospital,
            'selectedSector' => $this->selectedSector,
            'currentHospitalName' => $this->currentHospitalName,
            'loading' => $this->loading,
            'lastRefresh' => $this->lastRefresh,
        ]);
    }
}