<?php

namespace App\Livewire;

use App\Services\TasyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\Attributes\Isolate;

#[Isolate]
class ExpiredScalesModal extends Component
{
    public bool $isOpen = false;
    public array $patientsWithExpiredScales = [];
    public ?int $sectorId = null;
    public bool $loading = false;
    public int $totalExpired = 0;

    protected $listeners = ['openExpiredScalesModal' => 'open'];

    public function open($sectorId = null)
    {
        if ($sectorId) {
            $this->sectorId = (int) $sectorId;
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
            $this->loading = false;
            return;
        }

        $this->loading = true;

        try {
            $cacheKey = "expired_scales_sector_{$this->sectorId}_" . now()->format('YmdHi');

            $this->patientsWithExpiredScales = Cache::remember($cacheKey, 120, function() {
                $tasy = new TasyService();
                $patients = $tasy->getSectorPatientsForSbar($this->sectorId);

                $expiredList = [];

                foreach ($patients as $patient) {
                    if (!($patient['has_patient'] ?? false)) {
                        continue;
                    }

                    $expiredScales = [];

                    if ($this->isScaleExpired($patient, 'mews')) {
                        $expiredScales[] = [
                            'name' => 'MEWS',
                            'label' => 'Modificado de Alerta Precoce',
                            'freq' => 'Toda mudança de turno',
                            'last_value' => $patient['mews_score'] ?? null,
                            'last_shift' => $patient['mews_shift'] ?? null,
                            'last_timestamp' => $patient['mews_timestamp'] ?? null,
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'pews')) {
                        $expiredScales[] = [
                            'name' => 'PEWS',
                            'label' => 'Alerta Precoce Pediátrico',
                            'freq' => 'Toda mudança de turno',
                            'last_value' => $patient['pews_score'] ?? null,
                            'last_shift' => $patient['pews_shift'] ?? null,
                            'last_timestamp' => $patient['pews_timestamp'] ?? null,
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'braden')) {
                        $expiredScales[] = [
                            'name' => 'Braden',
                            'label' => 'Risco de Lesão por Pressão',
                            'freq' => 'Toda mudança de turno',
                            'last_value' => $patient['braden_score'] ?? null,
                            'last_shift' => $patient['braden_shift'] ?? null,
                            'last_timestamp' => $patient['braden_timestamp'] ?? null,
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'morse')) {
                        $expiredScales[] = [
                            'name' => 'Morse',
                            'label' => 'Risco de Queda',
                            'freq' => 'Toda mudança de turno',
                            'last_value' => $patient['morse_score'] ?? null,
                            'last_shift' => $patient['morse_shift'] ?? null,
                            'last_timestamp' => $patient['morse_timestamp'] ?? null,
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'pain')) {
                        $expiredScales[] = [
                            'name' => 'Dor',
                            'label' => 'Avaliação da Dor',
                            'freq' => 'Toda mudança de turno',
                            'last_value' => $patient['pain_score'] ?? null,
                            'last_shift' => $patient['pain_shift'] ?? null,
                            'last_timestamp' => $patient['pain_timestamp'] ?? null,
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'vte')) {
                        $expiredScales[] = [
                            'name' => 'TEV',
                            'label' => 'Risco de Tromboembolismo',
                            'freq' => 'Admissão / mudança clínica',
                            'last_value' => $patient['vte_score'] ?? null,
                            'last_shift' => $patient['vte_shift'] ?? null,
                            'last_timestamp' => $patient['vte_timestamp'] ?? null,
                        ];
                    }

                    if (!empty($expiredScales)) {
                        $expiredList[] = [
                            'bed'            => $patient['cd_unidade_basica'] ?? 'N/A',
                            'name'           => $patient['nm_pessoa_fisica'] ?? 'N/A',
                            'medical_record' => $patient['nr_prontuario'] ?? 'N/A',
                            'attendance'     => $patient['nr_atendimento'] ?? 'N/A',
                            'age'            => $patient['age'] ?? 'N/A',
                            'expired_scales' => $expiredScales,
                            'total_expired'  => count($expiredScales),
                            'priority'       => count($expiredScales) >= 4 ? 'critical' : (count($expiredScales) >= 2 ? 'high' : 'medium'),
                        ];
                    }
                }

                usort($expiredList, function($a, $b) {
                    $cmp = $b['total_expired'] - $a['total_expired'];
                    if ($cmp !== 0) return $cmp;
                    return strnatcmp($a['bed'], $b['bed']);
                });

                return $expiredList;
            });

            $this->totalExpired = count($this->patientsWithExpiredScales);

        } catch (\Exception $e) {
            Log::error('[ExpiredScalesModal] Error loading scales', [
                'sector_id' => $this->sectorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->patientsWithExpiredScales = [];
            $this->totalExpired = 0;
        } finally {
            $this->loading = false;
        }
    }

    private function isScaleExpired(array $patient, string $scale): bool
    {
        return (bool)($patient["{$scale}_needs_assessment"] ?? false);
    }

    public function refresh()
    {
        $cacheKey = "expired_scales_sector_{$this->sectorId}_" . now()->format('YmdHi');
        Cache::forget($cacheKey);

        $previousMinute = now()->subMinute()->format('YmdHi');
        Cache::forget("expired_scales_sector_{$this->sectorId}_{$previousMinute}");

        $this->loadExpiredScales();
    }

    public function render()
    {
        return view('sbar.patient.expired-scales-modal');
    }
}
