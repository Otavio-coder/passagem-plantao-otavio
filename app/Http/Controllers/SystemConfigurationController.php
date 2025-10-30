<?php

namespace App\Http\Controllers;

use App\Models\EMR\Core\Bed;
use App\Models\EMR\Core\Hospital;
use App\Models\EMR\Core\Sector;
use App\Models\System\SystemConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
     * Mostrar a tela de edição (carrega dados estruturais via Eloquent)
     */
    public function edit()
    {
        $cacheKey = 'system_config_structural_data';

        $data = Cache::remember($cacheKey, 1800, function() {
            return $this->getStructuralData();
        });

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
     * Retorna hospitais, setores e leitos usando apenas Eloquent
     */
    private function getStructuralData(): array
    {
        // Hospitals
        $hospitals = Hospital::whereIn('nr_sequencia', self::ALLOWED_HOSPITAL_IDS)
            ->where('ie_situacao', 'A')
            ->orderBy('ds_agrupamento')
            ->get()
            ->map(fn($h) => ['code' => (string)$h->nr_sequencia, 'name' => $h->ds_agrupamento])
            ->values()
            ->toArray();

        // Sectors that belong to allowed hospitals and have active beds
        $sectors = Sector::whereIn('nr_seq_agrupamento', self::ALLOWED_HOSPITAL_IDS)
            ->where('ie_situacao', 'A')
            ->orderBy('ds_setor_atendimento')
            ->get()
            ->filter(fn($s) => Bed::where('cd_setor_atendimento', $s->cd_setor_atendimento)->where('ie_situacao','A')->exists())
            ->map(fn($s) => ['code' => (string)$s->cd_setor_atendimento, 'name' => $s->ds_setor_atendimento, 'hospital_id' => (string)$s->nr_seq_agrupamento])
            ->values()
            ->toArray();

        // Beds for the sectors above
        $sectorCodes = array_column($sectors, 'code');
        $bedunits = [];
        if (!empty($sectorCodes)) {
            $beds = Bed::whereIn('cd_setor_atendimento', $sectorCodes)
                ->where('ie_situacao', 'A')
                ->orderBy('cd_setor_atendimento')
                ->orderBy('cd_unidade_basica')
                ->get();

            $bedunits = $beds->map(fn($b) => ['code' => (string)$b->cd_unidade_basica, 'name' => (string)$b->cd_unidade_basica, 'cd_setor_atendimento' => (string)$b->cd_setor_atendimento])
                ->values()
                ->toArray();
        }

        return [
            'hospitals' => $hospitals,
            'sectors' => $sectors,
            'bedunits' => $bedunits,
        ];
    }

    /**
     * Retorna setores por hospitais (Eloquent)
     */
    public function getSectorsByHospital(Request $request)
    {
        $hospitalIds = array_intersect(
            array_map('strval', $request->input('hospital_ids', [])),
            array_map('strval', self::ALLOWED_HOSPITAL_IDS)
        );

        if (empty($hospitalIds)) {
            return response()->json([]);
        }

        $cacheKey = 'sectors_by_hospitals_' . implode('_', $hospitalIds);

        $sectors = Cache::remember($cacheKey, 1200, function() use ($hospitalIds) {
            return Sector::whereIn('nr_seq_agrupamento', $hospitalIds)
                ->where('ie_situacao', 'A')
                ->get()
                ->filter(fn($s) => Bed::where('cd_setor_atendimento', $s->cd_setor_atendimento)->where('ie_situacao','A')->exists())
                ->map(fn($s) => ['code' => (string)$s->cd_setor_atendimento, 'name' => $s->ds_setor_atendimento, 'hospital_id' => (string)$s->nr_seq_agrupamento])
                ->values()
                ->toArray();
        });

        return response()->json($sectors);
    }

    /**
     * Retorna leitos por setor (valida setores contra hospitais permitidos inline)
     */
    public function getBedsBySector(Request $request)
    {
        $sectorIds = array_map('strval', $request->input('sector_ids', []));
        if (empty($sectorIds)) {
            return response()->json([]);
        }

        // Ensure sectors belong to allowed hospitals
        $validSectors = Sector::whereIn('cd_setor_atendimento', $sectorIds)
            ->whereIn('nr_seq_agrupamento', array_map('strval', self::ALLOWED_HOSPITAL_IDS))
            ->where('ie_situacao', 'A')
            ->pluck('cd_setor_atendimento')
            ->map(fn($v) => (string)$v)
            ->values()
            ->toArray();

        if (empty($validSectors)) {
            return response()->json([]);
        }

        $cacheKey = 'beds_by_sectors_' . md5(implode(',', $validSectors));

        $beds = Cache::remember($cacheKey, 600, function() use ($validSectors) {
            return Bed::whereIn('cd_setor_atendimento', $validSectors)
                ->where('ie_situacao', 'A')
                ->orderBy('cd_setor_atendimento')
                ->orderBy('cd_unidade_basica')
                ->get()
                ->map(fn($b) => ['code' => (string)$b->cd_unidade_basica, 'name' => (string)$b->cd_unidade_basica, 'cd_setor_atendimento' => (string)$b->cd_setor_atendimento])
                ->values()
                ->toArray();
        });

        return response()->json($beds);
    }

    /**
     * Atualiza as configurações usando apenas Eloquent models (menor número de funções)
     */
    public function update(Request $request)
    {
        try {
            \DB::beginTransaction();

            // remove todas as configurações atuais
            SystemConfiguration::whereIn('configuration_type', ['hospital','sector','bed'])->delete();

            // hospitals selecionados (filtra contra a lista permitida)
            $hospitalCodes = array_values(array_intersect($request->input('hospital_codes', []), self::ALLOWED_HOSPITAL_IDS));

            foreach ($hospitalCodes as $code) {
                $h = Hospital::where('nr_sequencia', (string)$code)->where('ie_situacao','A')->first();
                if ($h) {
                    SystemConfiguration::create([
                        'hospital_code' => (string)$h->nr_sequencia,
                        'hospital_name' => $h->ds_agrupamento ?? null,
                        'configuration_type' => 'hospital',
                        'is_active' => true,
                        'configured_by' => auth()->user()->id ?? 0,
                    ]);
                }
            }

            // setores selecionados: apenas registre setores que pertencem aos hospitais escolhidos
            $sectorCodes = array_map('strval', $request->input('sector_codes', []));
            if (!empty($sectorCodes) && !empty($hospitalCodes)) {
                $validSectors = Sector::whereIn('cd_setor_atendimento', $sectorCodes)
                    ->whereIn('nr_seq_agrupamento', array_map('strval', $hospitalCodes))
                    ->where('ie_situacao','A')
                    ->get();

                foreach ($validSectors as $s) {
                    SystemConfiguration::create([
                        'sector_code' => (string)$s->cd_setor_atendimento,
                        'sector_name' => $s->ds_setor_atendimento ?? null,
                        'configuration_type' => 'sector',
                        'is_active' => true,
                        'configured_by' => auth()->user()->id ?? 0,
                    ]);
                }
            }

            // leitos selecionados: espera-se array de strings 'bed|sector'
            $bedCodes = $request->input('bed_codes', []);
            if (!empty($bedCodes)) {
                foreach ($bedCodes as $comp) {
                    if (!str_contains($comp, '|')) continue;
                    [$code, $sector] = explode('|', $comp, 2);
                    // valida se o setor foi configurado ou pertence aos hospitais permitidos
                    $sectorExists = Sector::where('cd_setor_atendimento', (string)$sector)
                        ->whereIn('nr_seq_agrupamento', array_map('strval', self::ALLOWED_HOSPITAL_IDS))
                        ->where('ie_situacao','A')
                        ->exists();

                    if (! $sectorExists) continue;

                    $b = Bed::where('cd_unidade_basica', (string)$code)
                        ->where('cd_setor_atendimento', (string)$sector)
                        ->where('ie_situacao','A')
                        ->first();

                    if ($b) {
                        SystemConfiguration::create([
                            'bed_code' => (string)$b->cd_unidade_basica,
                            'bed_name' => $b->cd_unidade_basica ?? null,
                            'sector_code' => (string)$b->cd_setor_atendimento,
                            'configuration_type' => 'bed',
                            'is_active' => true,
                            'configured_by' => auth()->user()->id ?? 0,
                        ]);
                    }
                }
            }

            \DB::commit();

            $this->clearRelatedCaches();

            return redirect()->route('system-configuration.index')->with('success', 'Configuração atualizada com sucesso!');

        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('SystemConfiguration update error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return redirect()->route('system-configuration.index')->with('error', 'Erro ao atualizar configuração: ' . $e->getMessage());
        }
    }

    /**
     * Limpa caches relacionados
     */
    private function clearRelatedCaches(): void
    {
        $keys = [
            'allowed_hospital_codes',
            'allowed_sector_codes',
            'allowed_bed_codes',
            'sbar_config',
        ];

        foreach ($keys as $k) Cache::forget($k);
    }

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

