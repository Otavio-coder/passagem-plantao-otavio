<?php

namespace App\Livewire;

use App\Services\TasyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class EscalasVencidasModal extends Component
{
    public bool $isOpen = false;
    public array $patientsWithExpiredScales = [];
    public int $sectorId = 0;
    public bool $loading = false;
    public int $totalExpired = 0;

    protected $listeners = ['openEscalasVencidasModal' => 'open'];

    public function open($sectorId = null)
    {
        if ($sectorId) {
            $this->sectorId = $sectorId;
        }

        $this->isOpen = true;
        $this->loadExpiredScales();
    }

    public function close()
    {
        $this->isOpen = false;
    }

    public function loadExpiredScales()
    {
        if (!$this->sectorId) {
            return;
        }

        $this->loading = true;

        try {
            $cacheKey = "expired_scales_sector_{$this->sectorId}";

            $this->patientsWithExpiredScales = Cache::remember($cacheKey, 300, function() {
                $tasy = new TasyService();
                $patients = $tasy->getSectorPatientsForSbar($this->sectorId);

                $expiredList = [];

                foreach ($patients as $patient) {
                    if (!($patient['has_patient'] ?? false)) {
                        continue;
                    }

                    $expiredScales = [];

                    // Verificar MEWS
                    if ($this->isScaleExpired($patient, 'mews')) {
                        $expiredScales[] = [
                            'name' => 'MEWS',
                            'last_value' => $patient['mews_score'] ?? '-',
                            'shift' => $patient['mews_shift'] ?? 'N/A',
                        ];
                    }

                    // Verificar Braden
                    if ($this->isScaleExpired($patient, 'braden')) {
                        $expiredScales[] = [
                            'name' => 'Braden',
                            'last_value' => $patient['braden_score'] ?? '-',
                            'shift' => $patient['braden_shift'] ?? 'N/A',
                        ];
                    }

                    // Verificar Morse
                    if ($this->isScaleExpired($patient, 'morse')) {
                        $expiredScales[] = [
                            'name' => 'Morse',
                            'last_value' => $patient['morse_score'] ?? '-',
                            'shift' => $patient['morse_shift'] ?? 'N/A',
                        ];
                    }

                    // Verificar Dor
                    if ($this->isScaleExpired($patient, 'dor')) {
                        $expiredScales[] = [
                            'name' => 'Dor',
                            'last_value' => $patient['dor_score'] ?? '-',
                            'shift' => $patient['dor_shift'] ?? 'N/A',
                        ];
                    }

                    // Verificar TEV
                    if ($this->isScaleExpired($patient, 'tev')) {
                        $expiredScales[] = [
                            'name' => 'TEV',
                            'last_value' => $patient['tev_score'] ?? '-',
                            'shift' => $patient['tev_shift'] ?? 'N/A',
                        ];
                    }

                    if (!empty($expiredScales)) {
                        $expiredList[] = [
                            'leito' => $patient['cd_unidade_basica'] ?? 'N/A',
                            'nome' => $patient['nm_pessoa_fisica'] ?? 'N/A',
                            'prontuario' => $patient['nr_prontuario'] ?? 'N/A',
                            'atendimento' => $patient['nr_atendimento'] ?? 'N/A',
                            'idade' => $patient['age'] ?? 'N/A',
                            'expired_scales' => $expiredScales,
                            'total_expired' => count($expiredScales),
                        ];
                    }
                }

                // Ordenar por número de escalas vencidas (mais críticos primeiro)
                usort($expiredList, function($a, $b) {
                    $cmp = $b['total_expired'] - $a['total_expired'];
                    if ($cmp !== 0) return $cmp;
                    return strnatcmp($a['leito'], $b['leito']);
                });

                return $expiredList;
            });

            $this->totalExpired = count($this->patientsWithExpiredScales);

        } catch (\Exception $e) {
            Log::error('[EscalasVencidasModal] Erro ao carregar escalas', [
                'sector_id' => $this->sectorId,
                'error' => $e->getMessage()
            ]);
            $this->patientsWithExpiredScales = [];
            $this->totalExpired = 0;
        } finally {
            $this->loading = false;
        }
    }

    private function isScaleExpired(array $patient, string $scale): bool
    {
        // Verifica se a flag de needs_assessment está setada
        $needsAssessment = $patient["{$scale}_needs_assessment"] ?? false;

        // Também verifica se o score está vazio/nulo
        $score = $patient["{$scale}_score"] ?? null;
        $hasNoScore = empty($score) || $score === '-' || $score === 'N/A';

        // Verifica se o turno é diferente do atual (escala antiga)
        $scaleShift = $patient["{$scale}_shift"] ?? null;
        $currentShiftInfo = getShiftInfo();
        $currentShift = strtoupper(substr($currentShiftInfo['shift'], 0, 1));

        $isOldShift = !empty($scaleShift) && $scaleShift !== $currentShift;

        return $needsAssessment || $hasNoScore || $isOldShift;
    }

    public function refresh()
    {
        $cacheKey = "expired_scales_sector_{$this->sectorId}";
        Cache::forget($cacheKey);
        $this->loadExpiredScales();
    }

    public function render()
    {
        return view('livewire.escalas-vencidas-modal');
    }
}
