<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\System\SystemConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SystemConfigurationController extends Controller
{
    public function index()
    {
        return $this->edit();
    }

    public function edit()
    {
        $allowedHospitalIds = [1,2,3,4,5,6,7,8,10,18,25];

        $hospitals = DB::connection('tasy')->select("
            SELECT nr_sequencia AS code, ds_agrupamento AS name
            FROM TASY.AGRUPAMENTO_SETOR
            WHERE ie_situacao = 'A'
            AND nr_sequencia IN (" . implode(',', $allowedHospitalIds) . ")
            ORDER BY ds_agrupamento
        ");

        $sectors = DB::connection('tasy')->select("
            SELECT 
                cd_setor_atendimento AS code, 
                ds_setor_atendimento AS name,
                nr_seq_agrupamento AS hospital_id
            FROM TASY.SETOR_ATENDIMENTO
            WHERE ie_situacao = 'A'
            ORDER BY ds_setor_atendimento
        ");

        $bedunits = DB::connection('tasy')->select("
            SELECT 
                cd_unidade_basica AS code, 
                cd_unidade_basica AS name,
                cd_setor_atendimento
            FROM TASY.UNIDADE_ATENDIMENTO
            WHERE ie_situacao = 'A'
            ORDER BY cd_unidade_basica
        ");

        // Busca selecionados do banco de configurações
        $selectedHospitals = SystemConfiguration::allowedHospitalCodes();
        $selectedSectors   = SystemConfiguration::allowedSectorCodes();
        $selectedBeds      = SystemConfiguration::allowedBedCodes();

        return view('system-configuration.index', compact(
            'hospitals','sectors','bedunits',
            'selectedHospitals','selectedSectors','selectedBeds'
        ));
    }

    public function getSectorsByHospital(Request $request)
    {
        $hospitalIds = $request->input('hospital_ids', []);
        $sectors = DB::connection('tasy')->select("
            SELECT 
                s.cd_setor_atendimento AS code, 
                s.ds_setor_atendimento AS name,
                s.nr_seq_agrupamento AS hospital_id
            FROM TASY.SETOR_ATENDIMENTO s
            WHERE s.ie_situacao = 'A'
            AND EXISTS (
                SELECT 1 FROM TASY.UNIDADE_ATENDIMENTO u
                WHERE u.cd_setor_atendimento = s.cd_setor_atendimento
                    AND u.ie_situacao = 'A'
            )
            ORDER BY s.ds_setor_atendimento
        ");
        return response()->json($sectors);
    }

    public function getBedsBySector(Request $request)
    {
        $sectorIds = $request->input('sector_ids', []);
        $beds = DB::connection('tasy')->select("
            SELECT cd_unidade_basica AS code, cd_unidade_basica AS name, cd_setor_atendimento
            FROM TASY.UNIDADE_ATENDIMENTO
            WHERE ie_situacao = 'A'
            AND cd_setor_atendimento IN (" . implode(',', $sectorIds) . ")
            ORDER BY cd_unidade_basica
        ");
        return response()->json($beds);
    }

    // Atualiza as opções selecionadas pelo admin
    public function update(Request $request)
    {
        Log::debug('SystemConfig update payload', $request->all());

        SystemConfiguration::whereIn('configuration_type', ['hospital','sector','bed'])->delete();

        // Hospitais
        foreach ($request->input('hospital_codes', []) as $code) {
            $hospital = DB::connection('tasy')->selectOne("
                SELECT nr_sequencia, ds_agrupamento AS name
                FROM TASY.AGRUPAMENTO_SETOR
                WHERE nr_sequencia = :code
            ", ['code' => $code]);
            $name = $hospital->name ?? null;

            SystemConfiguration::create([
                'hospital_code'      => $code,
                'hospital_name'      => $name,
                'configuration_type' => 'hospital',
                'is_active'          => true,
                'configured_by'      => auth()->user()->id ?? 0,
            ]);
        }

        $allowedHospitals = $request->input('hospital_codes', []);
        foreach ($request->input('sector_codes', []) as $code) {
            $sector = DB::connection('tasy')->selectOne("
                SELECT cd_setor_atendimento, ds_setor_atendimento AS name, nr_seq_agrupamento AS hospital_id
                FROM TASY.SETOR_ATENDIMENTO
                WHERE cd_setor_atendimento = :code
            ", ['code' => $code]);
            $name = $sector->name ?? null;

            // Só salva setor se o hospital estiver permitido
            if (!in_array($sector->hospital_id, $allowedHospitals)) {
                continue;
            }

            SystemConfiguration::create([
                'sector_code'        => $code,
                'sector_name'        => $name,
                'configuration_type' => 'sector',
                'is_active'          => true,
                'configured_by'      => auth()->user()->id ?? 0,
            ]);
        }

        $allowedSectors = $request->input('sector_codes', []);
        foreach ($request->input('bed_codes', []) as $bedComposite) {
            [$code, $sector] = explode('|', $bedComposite);
            $bed = DB::connection('tasy')->selectOne("
                SELECT cd_unidade_basica, cd_unidade_basica AS name, cd_setor_atendimento
                FROM TASY.UNIDADE_ATENDIMENTO
                WHERE cd_unidade_basica = :code AND cd_setor_atendimento = :sector
            ", ['code' => $code, 'sector' => $sector]);
            $name = $bed->name ?? null;

            if (!in_array($sector, $allowedSectors)) {
                continue;
            }

            SystemConfiguration::create([
                'bed_code'           => $code,
                'bed_name'           => $name,
                'sector_code'        => $sector,
                'configuration_type' => 'bed',
                'is_active'          => true,
                'configured_by'      => auth()->user()->id ?? 0,
            ]);
        }

        Cache::forget('allowed_sectors_list');
        Cache::forget('hospitals_with_sectors');

        return redirect()
            ->route('system-configuration.index')
            ->with('success','Configuração atualizada!');
    }
}