<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\System\SystemConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SystemConfigurationController extends Controller
{
    /**
     * IDs de hospitais permitidos (configuração base)
     */
    private const ALLOWED_HOSPITAL_IDS = [1,2,3,4,5,6,7,8,10,18,25];

    public function index()
    {
        return $this->edit();
    }

    /**
     * OTIMIZADO: Carrega dados estruturais com cache e queries otimizadas
     */
    public function edit()
    {
        $cacheKey = 'system_config_structural_data_v2';
        
        $data = Cache::remember($cacheKey, 1800, function() {
            return $this->loadStructuralData();
        });

        // Busca selecionados do banco (não cachear pois muda frequentemente)
        $selectedHospitals = SystemConfiguration::allowedHospitalCodes();
        $selectedSectors   = SystemConfiguration::allowedSectorCodes();
        $selectedBeds      = SystemConfiguration::allowedBedCodes();

        return view('system-configuration.index', array_merge($data, [
            'selectedHospitals' => $selectedHospitals,
            'selectedSectors'   => $selectedSectors,
            'selectedBeds'      => $selectedBeds
        ]));
    }

    /**
     * NOVA: Carrega dados estruturais de forma otimizada
     */
    private function loadStructuralData(): array
    {
        $hospitalIds = self::ALLOWED_HOSPITAL_IDS;
        $placeholders = implode(',', $hospitalIds);

        // Query única para hospitais
        $hospitals = DB::connection('tasy')->select("
            SELECT nr_sequencia AS code, ds_agrupamento AS name
            FROM TASY.AGRUPAMENTO_SETOR
            WHERE ie_situacao = 'A'
            AND nr_sequencia IN ({$placeholders})
            ORDER BY ds_agrupamento
        ");

        // Query única para setores com filtro de hospitais permitidos
        $sectors = DB::connection('tasy')->select("
            SELECT 
                cd_setor_atendimento AS code, 
                ds_setor_atendimento AS name,
                nr_seq_agrupamento AS hospital_id
            FROM TASY.SETOR_ATENDIMENTO
            WHERE ie_situacao = 'A'
            AND nr_seq_agrupamento IN ({$placeholders})
            AND EXISTS (
                SELECT 1 FROM TASY.UNIDADE_ATENDIMENTO u
                WHERE u.cd_setor_atendimento = SETOR_ATENDIMENTO.cd_setor_atendimento
                    AND u.ie_situacao = 'A'
            )
            ORDER BY ds_setor_atendimento
        ");

        // Query única para leitos com filtro de setores válidos
        $sectorCodes = collect($sectors)->pluck('code')->toArray();
        $bedunits = [];
        
        if (!empty($sectorCodes)) {
            $sectorPlaceholders = implode(',', $sectorCodes);
            $bedunits = DB::connection('tasy')->select("
                SELECT 
                    cd_unidade_basica AS code, 
                    cd_unidade_basica AS name,
                    cd_setor_atendimento
                FROM TASY.UNIDADE_ATENDIMENTO
                WHERE ie_situacao = 'A'
                AND cd_setor_atendimento IN ({$sectorPlaceholders})
                ORDER BY cd_setor_atendimento, cd_unidade_basica
            ");
        }

        return [
            'hospitals' => $hospitals,
            'sectors' => $sectors,
            'bedunits' => $bedunits
        ];
    }

    /**
     * OTIMIZADO: Busca setores por hospital com cache e validação
     */
    public function getSectorsByHospital(Request $request)
    {
        $hospitalIds = array_intersect(
            $request->input('hospital_ids', []), 
            self::ALLOWED_HOSPITAL_IDS
        );

        if (empty($hospitalIds)) {
            return response()->json([]);
        }

        $cacheKey = 'sectors_by_hospitals_' . implode('_', sort($hospitalIds));
        
        $sectors = Cache::remember($cacheKey, 1200, function() use ($hospitalIds) {
            $placeholders = implode(',', $hospitalIds);
            
            return DB::connection('tasy')->select("
                SELECT 
                    s.cd_setor_atendimento AS code, 
                    s.ds_setor_atendimento AS name,
                    s.nr_seq_agrupamento AS hospital_id
                FROM TASY.SETOR_ATENDIMENTO s
                WHERE s.ie_situacao = 'A'
                AND s.nr_seq_agrupamento IN ({$placeholders})
                AND EXISTS (
                    SELECT 1 FROM TASY.UNIDADE_ATENDIMENTO u
                    WHERE u.cd_setor_atendimento = s.cd_setor_atendimento
                        AND u.ie_situacao = 'A'
                )
                ORDER BY s.ds_setor_atendimento
            ");
        });

        return response()->json($sectors);
    }

    /**
     * OTIMIZADO: Busca leitos por setor com validação e cache
     */
    public function getBedsBySector(Request $request)
    {
        $sectorIds = $request->input('sector_ids', []);
        
        if (empty($sectorIds)) {
            return response()->json([]);
        }

        // Valida se os setores pertencem a hospitais permitidos
        $validSectors = $this->validateSectorIds($sectorIds);
        
        if (empty($validSectors)) {
            return response()->json([]);
        }

        $cacheKey = 'beds_by_sectors_' . md5(implode(',', sort($validSectors)));
        
        $beds = Cache::remember($cacheKey, 600, function() use ($validSectors) {
            $placeholders = implode(',', $validSectors);
            
            return DB::connection('tasy')->select("
                SELECT 
                    cd_unidade_basica AS code, 
                    cd_unidade_basica AS name, 
                    cd_setor_atendimento
                FROM TASY.UNIDADE_ATENDIMENTO
                WHERE ie_situacao = 'A'
                AND cd_setor_atendimento IN ({$placeholders})
                ORDER BY cd_setor_atendimento, cd_unidade_basica
            ");
        });

        return response()->json($beds);
    }

    /**
     * NOVA: Valida se setores pertencem a hospitais permitidos
     */
    private function validateSectorIds(array $sectorIds): array
    {
        if (empty($sectorIds)) {
            return [];
        }

        $placeholders = implode(',', $sectorIds);
        $allowedHospitals = implode(',', self::ALLOWED_HOSPITAL_IDS);

        $validSectors = DB::connection('tasy')->select("
            SELECT cd_setor_atendimento
            FROM TASY.SETOR_ATENDIMENTO
            WHERE cd_setor_atendimento IN ({$placeholders})
            AND nr_seq_agrupamento IN ({$allowedHospitals})
            AND ie_situacao = 'A'
        ");

        return array_column($validSectors, 'cd_setor_atendimento');
    }

    /**
     * OTIMIZADO: Update com validações e limpeza de cache inteligente
     */
    public function update(Request $request)
    {
        try {
            DB::beginTransaction();
            
            // Limpa configurações existentes
            SystemConfiguration::whereIn('configuration_type', ['hospital','sector','bed'])->delete();

            // Processa hospitais
            $hospitalCodes = array_intersect($request->input('hospital_codes', []), self::ALLOWED_HOSPITAL_IDS);
            $this->processHospitals($hospitalCodes);

            // Processa setores (validando se pertencem aos hospitais selecionados)
            $sectorCodes = $request->input('sector_codes', []);
            $this->processSectors($sectorCodes, $hospitalCodes);

            // Processa leitos (validando se pertencem aos setores selecionados)
            $bedCodes = $request->input('bed_codes', []);
            $this->processBeds($bedCodes, $sectorCodes);

            DB::commit();

            // Limpa caches relacionados de forma inteligente
            $this->clearRelatedCaches();

            return redirect()
                ->route('system-configuration.index')
                ->with('success', 'Configuração atualizada com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('SystemConfiguration: Erro durante atualização', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()
                ->route('system-configuration.index')
                ->with('error', 'Erro ao atualizar configuração: ' . $e->getMessage());
        }
    }

    /**
     * NOVA: Processa hospitais com validação
     */
    private function processHospitals(array $hospitalCodes): void
    {
        foreach ($hospitalCodes as $code) {
            $hospital = DB::connection('tasy')->selectOne("
                SELECT nr_sequencia, ds_agrupamento AS name
                FROM TASY.AGRUPAMENTO_SETOR
                WHERE nr_sequencia = :code
                AND ie_situacao = 'A'
            ", ['code' => $code]);

            if ($hospital) {
                SystemConfiguration::create([
                    'hospital_code'      => $code,
                    'hospital_name'      => $hospital->name,
                    'configuration_type' => 'hospital',
                    'is_active'          => true,
                    'configured_by'      => auth()->user()->id ?? 0,
                ]);
            }
        }
    }

    /**
     * NOVA: Processa setores com validação hierárquica
     */
    private function processSectors(array $sectorCodes, array $allowedHospitals): void
    {
        if (empty($allowedHospitals)) {
            return;
        }

        $allowedHospitalsStr = implode(',', $allowedHospitals);
        
        foreach ($sectorCodes as $code) {
            $sector = DB::connection('tasy')->selectOne("
                SELECT 
                    cd_setor_atendimento, 
                    ds_setor_atendimento AS name, 
                    nr_seq_agrupamento AS hospital_id
                FROM TASY.SETOR_ATENDIMENTO
                WHERE cd_setor_atendimento = :code
                AND nr_seq_agrupamento IN ({$allowedHospitalsStr})
                AND ie_situacao = 'A'
            ", ['code' => $code]);

            if ($sector) {
                SystemConfiguration::create([
                    'sector_code'        => $code,
                    'sector_name'        => $sector->name,
                    'configuration_type' => 'sector',
                    'is_active'          => true,
                    'configured_by'      => auth()->user()->id ?? 0,
                ]);
            }
        }
    }

    /**
     * NOVA: Processa leitos com validação hierárquica
     */
    private function processBeds(array $bedCodes, array $allowedSectors): void
    {
        if (empty($allowedSectors)) {
            return;
        }

        foreach ($bedCodes as $bedComposite) {
            if (!str_contains($bedComposite, '|')) {
                continue;
            }

            [$code, $sector] = explode('|', $bedComposite, 2);
            
            // Valida se o setor está permitido
            if (!in_array($sector, $allowedSectors)) {
                continue;
            }

            $bed = DB::connection('tasy')->selectOne("
                SELECT 
                    cd_unidade_basica, 
                    cd_unidade_basica AS name, 
                    cd_setor_atendimento
                FROM TASY.UNIDADE_ATENDIMENTO
                WHERE cd_unidade_basica = :code 
                AND cd_setor_atendimento = :sector
                AND ie_situacao = 'A'
            ", ['code' => $code, 'sector' => $sector]);

            if ($bed) {
                SystemConfiguration::create([
                    'bed_code'           => $code,
                    'bed_name'           => $bed->name,
                    'sector_code'        => $sector,
                    'configuration_type' => 'bed',
                    'is_active'          => true,
                    'configured_by'      => auth()->user()->id ?? 0,
                ]);
            }
        }
    }

    /**
     * NOVA: Limpa caches relacionados de forma inteligente
     */
    private function clearRelatedCaches(): void
    {
        $cachesToClear = [
            // Caches específicos do SystemConfiguration
            'allowed_hospital_codes',
            'allowed_sector_codes',
            'allowed_bed_codes',
            
            // Caches estruturais
            'system_config_structural_data_v2',
            'sbar_hierarchical_structure_v2',
            'allowed_sectors_list',
            'hospitals_with_sectors',
            
            // Caches de configuração SBAR
            'sbar_config_v3',
            'sbar_config_forever',
        ];

        foreach ($cachesToClear as $key) {
            Cache::forget($key);
        }

        // Limpa caches com padrão (sectors_by_hospitals_*, beds_by_sectors_*)
        $this->clearPatternCaches();
    }

    /**
     * NOVA: Endpoint para limpar caches manualmente (útil para debugging)
     */
    public function clearCaches()
    {
        if (!auth()->user() || !auth()->user()->can('manage-system-configuration')) {
            abort(403);
        }

        $this->clearRelatedCaches();

        return response()->json([
            'success' => true,
            'message' => 'Caches limpos com sucesso'
        ]);
    }

    /**
     * NOVA: Endpoint para estatísticas da configuração
     */
    public function getStats()
    {
        $stats = [
            'hospitals_configured' => SystemConfiguration::where('configuration_type', 'hospital')->where('is_active', true)->count(),
            'sectors_configured' => SystemConfiguration::where('configuration_type', 'sector')->where('is_active', true)->count(),
            'beds_configured' => SystemConfiguration::where('configuration_type', 'bed')->where('is_active', true)->count(),
            'last_update' => SystemConfiguration::latest('updated_at')->value('updated_at'),
        ];

        return response()->json($stats);
    }
}