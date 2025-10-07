<?php

namespace App\Livewire;

use App\Models\EMR\Patient;
use App\Models\EMR\Sector;
use App\Models\EMR\Hospital;
use App\Models\System\SystemConfiguration;
use Livewire\Component;
use Livewire\Attributes\Lazy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Lazy]
class SbarReport extends Component
{
    public $loading = false;
    public $loadingMessage = '';
    public $errorMessage = null;
    public $lastRefresh = null;
    
    // Dados principais
    public $hospitals = [];
    public $sectors = [];
    public $patients = [];
    
    public $selectedHospital = null;
    public $selectedSector = null;
    public $currentHospitalName = 'Carregando...';

    // Filtros
    public $mewsFilter = 'all';
    public $surgicalFilter = 'all';
    public $orderBy = 'bed';
    public $orderDirection = 'asc';

    // Model centralizada
    protected $patientModel;
    protected $hospitalModel;
    protected $sectorModel;

    //Inicialização dos modelos
    public function boot()
    {
        $this->patientModel = new Patient();
        $this->hospitalModel = new Hospital();
        $this->sectorModel = new Sector();
    }

    public function mount()
    {
        $this->loading = true;
        $this->loadingMessage = 'Inicializando sistema SBAR...';

        try {
            $this->loadInitialData();
        } catch (\Exception $e) {
            $this->errorMessage = "Erro durante inicialização: " . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    protected function loadInitialData()
    {
        // Busca os hospitais armazenados no SystemConfiguration.
        $allowedHospitals = SystemConfiguration::allowedHospitalCodes();
        $allowedSectors = SystemConfiguration::allowedSectorCodes();

        if (empty($allowedHospitals)) {
            $this->errorMessage = "Nenhum hospital configurado. Solicite ao gestor a configuração pelo painel administrativo.";
            return;
        }

        $this->loadHospitals($allowedHospitals);

        if (empty($this->hospitals)) {
            $this->errorMessage = "Nenhum hospital disponível.";
            return;
        }

        //Selecionada o primeiro hospital disponivel.
        $this->selectedHospital = $this->hospitals[0]['hospital_id'];
        $this->currentHospitalName = $this->hospitals[0]['hospital_name'];

        $this->loadSectorsForHospital($this->selectedHospital, $allowedSectors);

        if (!empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'];
            $this->loadPatients();
        } else {
            $this->errorMessage = "Nenhum setor válido encontrado para o hospital selecionado.";
        }
    }

    //Função auxiliar pra montar um array de hospitais.
    protected function loadHospitals($allowedHospitals)
    {
        $hospitalStats = $this->hospitalModel->getHospitalStatistics();
        
        $this->hospitals = collect($hospitalStats)
            ->filter(fn($h) => in_array($h->hospital_id, $allowedHospitals))
            ->map(fn($h) => [
                'hospital_id' => $h->hospital_id,
                'hospital_name' => $h->hospital_name
            ])
            ->values()
            ->toArray();
    }

    //Função auxiliar pra montar um array de setores.
    protected function loadSectorsForHospital($hospitalId, $allowedSectors)
    {
        $sectors = $this->sectorModel->getSectorsByHospital($hospitalId);
        
        $this->sectors = $sectors
            ->filter(fn($s) => in_array($s->cd_setor_atendimento, $allowedSectors))
            ->map(fn($s) => [
                'cd_setor_atendimento' => $s->cd_setor_atendimento,
                'ds_setor_atendimento' => $s->ds_setor_atendimento
            ])
            ->values()
            ->toArray();
    }

    /**
     * REMOVE MÉTODOS DUPLICADOS DE ESCALAS
     * Remove: enrichWithScalesStatus, getScalesStatusBatch, getPreviousScalesValues
     * A lógica agora está toda centralizada no PatientScales
     */
    public function loadPatients()
    {
        if (!$this->selectedSector) {
            $this->patients = [];
            return;
        }

        $this->loading = true;
        $this->loadingMessage = "Carregando pacientes do setor...";
        
        try {
            $patientsData = $this->patientModel->getSectorPatientsForSbar($this->selectedSector);
            
            $filteredPatients = $this->applyFiltersAndSort($patientsData);
            $this->patients = $this->stylePatients($filteredPatients);
            $this->lastRefresh = now()->format('H:i:s');
        } catch (\Exception $e) {
            $this->errorMessage = "Erro ao carregar pacientes: " . $e->getMessage();
            $this->patients = [];
        } finally {
            $this->loading = false;
            $this->loadingMessage = '';
        }
    }

    //Função auxiliar estilizar os pacientes conforme escala MEWS, tempo de internação e escalas.
    protected function stylePatients($patients)
    {
        return collect($patients)->map(function($patient) {
            // Filtrar pendências removendo medicações
            if (!empty($patient['pending_events'])) {
                $pendencias = array_filter(
                    explode(' | ', $patient['pending_events']),
                    fn($p) => trim($p) !== '' && !str_contains($p, '[Med]')
                );
                $patient['pending_events_filtered'] = array_values(array_slice($pendencias, 0, 10));
            } else {
                $patient['pending_events_filtered'] = [];
            }
            
            // GARANTIR que campos essenciais sempre existam - CORREÇÃO DO BUG
            $patient['alergias_detalhadas'] = $patient['alergias_detalhadas'] ?? '';
            $patient['motivos_isolamento'] = $patient['motivos_isolamento'] ?? '';
            
            // Preparar tooltips para alergias e isolamento
            if (!empty($patient['alergias_detalhadas'])) {
                $alergias = strip_tags($patient['alergias_detalhadas']);
                $patient['alergias_tooltip'] = strlen($alergias) > 50 ? substr($alergias, 0, 47) . '...' : $alergias;
            } else {
                $patient['alergias_tooltip'] = 'Nenhuma alergia registrada';
            }
            
            if (!empty($patient['motivos_isolamento'])) {
                $isolamento = strip_tags($patient['motivos_isolamento']);
                $patient['isolamento_tooltip'] = strlen($isolamento) > 50 ? substr($isolamento, 0, 47) . '...' : $isolamento;
            } else {
                $patient['isolamento_tooltip'] = 'Nenhum motivo de isolamento';
            }
            
            // Preparar escalas compactas - FORÇAR TEXTO NORMAL
            $patient['scales_compact'] = $this->prepareCompactScales($patient);
            
            return $this->applyPatientStyling($patient);
        })->toArray();
    }

    /**
     * Prepara escalas de forma compacta para exibição no card
     * Usa dados vindos da PatientScales com lógica consistente
     * 
     * CRITÉRIOS:
     * - Usa apenas escalas válidas (não nulas e não vazias)
     * - Mantém ordem de prioridade: MEWS/PEWS > Braden > Morse > Dor > TEV
     * - FORÇA TEXTO CINZA quando há bolinha piscante
     */
    protected function prepareCompactScales($patient)
    {
        $scales = [];
        $isPediatric = isset($patient['age']) && intval($patient['age']) < 16;

        // MEWS (adultos) ou PEWS (crianças) - MÁXIMA PRIORIDADE
        if (!$isPediatric && !empty($patient['ds_mews']) && $patient['ds_mews'] !== 'Sem avaliação nas últimas 24h') {
            $styling = function_exists('getMewsRiskStyling') ? getMewsRiskStyling($patient['ds_mews'], true) : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
            $increased = isset($patient['mews_increased']) ? $patient['mews_increased'] : false;
            
            $scales[] = [
                'label' => 'MEWS',
                'value' => $patient['ds_mews'],
                'bg_class' => $styling['bg'],
                'border_class' => $styling['border'],
                'text_class' => 'text-gray-800', // SEMPRE CINZA
                'increased' => $increased
            ];
        } elseif ($isPediatric && !empty($patient['ds_pews']) && $patient['ds_pews'] !== 'Sem avaliação nas últimas 24h') {
            $styling = function_exists('getPewsRiskStyling') ? getPewsRiskStyling($patient['ds_pews'], true) : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
            $increased = isset($patient['pews_increased']) ? $patient['pews_increased'] : false;
            
            $scales[] = [
                'label' => 'PEWS',
                'value' => $patient['ds_pews'],
                'bg_class' => $styling['bg'],
                'border_class' => $styling['border'],
                'text_class' => 'text-gray-800', // SEMPRE CINZA
                'increased' => $increased
            ];
        }

        // Braden - SEGUNDA PRIORIDADE
        if (!empty($patient['ds_braden']) && $patient['ds_braden'] !== 'Sem avaliação nas últimas 24h') {
            $styling = function_exists('getBradenRiskStyling') ? getBradenRiskStyling($patient['ds_braden'], true) : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
            $increased = isset($patient['braden_increased']) ? $patient['braden_increased'] : false;
            
            $scales[] = [
                'label' => 'Braden',
                'value' => $patient['ds_braden'],
                'bg_class' => $styling['bg'],
                'border_class' => $styling['border'],
                'text_class' => 'text-gray-800', // SEMPRE CINZA
                'increased' => $increased
            ];
        }

        // Morse - TERCEIRA PRIORIDADE
        if (!empty($patient['ds_morse']) && $patient['ds_morse'] !== 'Sem avaliação nas últimas 24h') {
            $styling = function_exists('getMorseRiskStyling') ? getMorseRiskStyling($patient['ds_morse'], true) : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
            $increased = isset($patient['morse_increased']) ? $patient['morse_increased'] : false;
            
            $scales[] = [
                'label' => 'Morse',
                'value' => $patient['ds_morse'],
                'bg_class' => $styling['bg'],
                'border_class' => $styling['border'],
                'text_class' => 'text-gray-800', // SEMPRE CINZA
                'increased' => $increased
            ];
        }

        // Dor - QUARTA PRIORIDADE
        if (!empty($patient['ds_dor']) && $patient['ds_dor'] !== 'Sem avaliação nas últimas 24h') {
            $styling = function_exists('getPainRiskStyling') ? getPainRiskStyling($patient['ds_dor'], true) : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
            $increased = isset($patient['dor_increased']) ? $patient['dor_increased'] : false;
            
            $scales[] = [
                'label' => 'Dor',
                'value' => $patient['ds_dor'],
                'bg_class' => $styling['bg'],
                'border_class' => $styling['border'],
                'text_class' => 'text-gray-800', // SEMPRE CINZA
                'increased' => $increased
            ];
        }

        // TEV - QUINTA PRIORIDADE
        if (!empty($patient['ds_tev']) && $patient['ds_tev'] !== 'Sem avaliação nas últimas 24h') {
            $styling = function_exists('getTevRiskStyling') ? getTevRiskStyling($patient['ds_tev'], true) : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
            $increased = isset($patient['tev_increased']) ? $patient['tev_increased'] : false;
            
            $scales[] = [
                'label' => 'TEV',
                'value' => $patient['ds_tev'],
                'bg_class' => $styling['bg'],
                'border_class' => $styling['border'],
                'text_class' => 'text-gray-800', // SEMPRE CINZA
                'increased' => $increased
            ];
        }

        // Retorna apenas as 3 primeiras escalas (espaço limitado no card)
        return array_slice($scales, 0, 3);
    }
    
    /**
     * Retorna o label amigável do turno
     */
    private function getShiftLabel($shift)
    {
        return match($shift) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            default => '',
        };
    }
    
    /**
     * Extrai o turno de uma string de escala
     */
    protected function extractShiftFromScale($scaleText)
    {
        if (empty($scaleText)) return null;
        
        // Procura por indicadores de turno
        if (preg_match('/(Manhã|manha|M)/i', $scaleText)) return 'manha';
        if (preg_match('/(Tarde|tarde|T)/i', $scaleText)) return 'tarde';
        if (preg_match('/(Noite|noite|N)/i', $scaleText)) return 'noite';
        
        return null;
    }
    
    /**
     * Aplica estilização baseada em MEWS e internação
     */
    protected function applyPatientStyling($patient)
    {
        // Always set style keys, even for empty beds
        if (!($patient['has_patient'] ?? false)) {
            $patient['gradient_class'] = 'from-gray-50 to-gray-100';
            $patient['border_class'] = 'border border-gray-300';
            $patient['text_color_class'] = 'text-gray-600';
            return $patient;
        }

        $isNewPatient = ($patient['internment_days'] ?? null) !== null && $patient['internment_days'] < 1;
        $mewsScore = $patient['mews_score'] ?? null;

        if ($isNewPatient) {
            $patient['gradient_class'] = 'from-green-50 to-green-100';
            $patient['border_class'] = 'border-2 border-green-400';
            $patient['text_color_class'] = 'text-green-800';
        } elseif ($mewsScore !== null && $mewsScore >= 5) {
            $patient['gradient_class'] = 'from-red-50 to-red-100';
            $patient['border_class'] = 'border-2 border-red-500';
            $patient['text_color_class'] = 'text-red-800';
        } elseif ($mewsScore !== null && $mewsScore == 4) {
            $patient['gradient_class'] = 'from-orange-50 to-orange-100';
            $patient['border_class'] = 'border-2 border-orange-500';
            $patient['text_color_class'] = 'text-orange-800';
        } elseif ($mewsScore !== null && $mewsScore == 3) {
            $patient['gradient_class'] = 'from-yellow-50 to-yellow-100';
            $patient['border_class'] = 'border-2 border-yellow-500';
            $patient['text_color_class'] = 'text-yellow-800';
        } else {
            // MEWS 0-2 ou sem MEWS - tom azul claro agradável
            $patient['gradient_class'] = 'from-blue-50 to-blue-100';
            $patient['border_class'] = 'border border-blue-200';
            $patient['text_color_class'] = 'text-blue-800';
        }

        return $patient;
    }

    //Função auxiliar aplicar filtros e ordenação.
    protected function applyFiltersAndSort($data)
    {
        $filtered = collect($data);

        // Filtro MEWS
        if ($this->mewsFilter !== 'all') {
            $filtered = $filtered->filter(function($patient) {
                if (!$patient['has_patient']) return false;
                
                $score = $patient['mews_score'];
                return match($this->mewsFilter) {
                    'critical' => $score !== null && $score >= 5,
                    'warning' => $score !== null && $score >= 3 && $score <= 4,
                    'normal' => $score === null || $score <= 2,
                    default => true
                };
            });
        }

        // Filtro cirúrgico
        if ($this->surgicalFilter === 'with_surgery') {
            $filtered = $filtered->filter(fn($patient) => 
                $patient['has_patient'] && $patient['has_surgery']
            );
        } elseif ($this->surgicalFilter === 'without_surgery') {
            $filtered = $filtered->filter(fn($patient) => 
                $patient['has_patient'] && !$patient['has_surgery']
            );
        }

        // Ordenação
        $filtered = $this->sortPatients($filtered);

        return $filtered->values()->toArray();
    }

    //Função auxiliar pra ordenar os pacientes.
    protected function sortPatients($collection)
    {
        $descending = $this->orderDirection === 'desc';

        return match($this->orderBy) {
            'bed' => $collection->sortBy(function($p) {
                return sprintf('%s-%03d', 
                    $p['cd_unidade_basica'] ?? '', 
                    $p['bed_sequence'] ?? 0
                );
            }, SORT_STRING, $descending),
            
            'mews' => $collection->sortBy(function($p) {
                return $p['has_patient'] ? ($p['mews_score'] ?? -1) : -999;
            }, SORT_NUMERIC, $descending),
            
            'name' => $collection->sortBy(function($p) {
                return strtolower($p['nm_pessoa_fisica'] ?? 'zzz');
            }, SORT_STRING, $descending),
            
            'prontuario' => $collection->sortBy(function($p) {
                return $p['nr_prontuario'] ?? 'zzz';
            }, SORT_STRING, $descending),
            
            'internment' => $collection->sortBy(function($p) {
                return $p['internment_days'] ?? -1;
            }, SORT_NUMERIC, $descending),
            
            'age' => $collection->sortBy(function($p) {
                return $p['age'] ?? 0;
            }, SORT_NUMERIC, $descending),
            
            default => $collection->sortBy(function($p) {
                return sprintf('%s-%03d', 
                    $p['cd_unidade_basica'] ?? '', 
                    $p['bed_sequence'] ?? 0
                );
            }, SORT_STRING, $descending)
        };
    }

    //Função para mudar o hospital selecionado
    public function changeHospital($hospitalId)
    {
        $this->loading = true;
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;

        $hospital = collect($this->hospitals)->firstWhere('hospital_id', $hospitalId);
        $this->currentHospitalName = $hospital['hospital_name'] ?? 'Hospital';

        $allowedSectors = SystemConfiguration::allowedSectorCodes();
        $this->loadSectorsForHospital($hospitalId, $allowedSectors);

        if (!empty($this->sectors)) {
            $this->selectedSector = $this->sectors[0]['cd_setor_atendimento'];
            $this->loadPatients();
        } else {
            $this->patients = [];
            $this->loading = false;
        }
    }

    //Função para mudar o setor selecionado
    public function changeSector($sectorId)
    {
        $this->selectedSector = $sectorId;
        $this->loadPatients();
    }

    //Função para aplicar filtro MEWS
    public function applyMewsFilter($filter)
    {
        $this->mewsFilter = $filter;
        $this->loadPatients();
        session()->flash('message', 'Filtro MEWS aplicado!');
    }

    //Função para aplicar filtro cirúrgico
    public function applySurgicalFilter($filter)
    {
        $this->surgicalFilter = $filter;
        $this->loadPatients();
        session()->flash('message', 'Filtro cirúrgico aplicado!');
    }

    //Função para aplicar ordenação
    public function applyOrderBy($field)
    {
        $this->orderBy = $field;
        $this->loadPatients();
        session()->flash('message', 'Ordenação aplicada!');
    }

    //Função para alternar a direção da ordenação
    public function toggleOrderDirection()
    {
        $this->orderDirection = $this->orderDirection === 'asc' ? 'desc' : 'asc';
        $this->loadPatients();
        session()->flash('message', 'Direção de ordenação alterada!');
    }

    //Função para resetar filtros
    public function resetFilters()
    {
        $this->mewsFilter = 'all';
        $this->surgicalFilter = 'all';
        $this->orderBy = 'bed';
        $this->orderDirection = 'asc';
        $this->loadPatients();
        session()->flash('message', 'Filtros resetados com sucesso!');
    }

    //Função para atualizar dados
    public function refreshData()
    {
        $this->loading = true;
        $this->loadingMessage = "Atualizando dados...";
        $this->errorMessage = null;
        
        // Limpa cache do setor antes de recarregar
        $this->patientModel->clearSectorCache($this->selectedSector);
        
        $this->loadPatients();
    }

    //Função para atualizar dados automaticamente
    public function autoRefresh()
    {
        $this->loadPatients();
        $this->dispatch('auto-refreshed');
    }

    //Função para exibir o placeholder
    public function placeholder()
    {
        return view('livewire.sbar-report-placeholder');
    }

    //Função para renderizar o componente
    public function render()
    {
        return view('livewire.sbar-report', [
            'hospitals' => $this->hospitals,
            'sectors' => $this->sectors,
            'patients' => $this->patients,
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