<?php

namespace App\Livewire;

use App\Services\TasyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ExpiredScalesModal extends Component
{
    public bool $isOpen = false;
    public array $patientsWithExpiredScales = [];
    public int $sectorId = 0;
    public bool $loading = false;
    public int $totalExpired = 0;

    protected $listeners = ['openExpiredScalesModal' => 'open'];

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
                            'last_value' => $patient['mews_score'] ?? '-',
                            'shift' => $patient['mews_shift'] ?? 'N/A',
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'pews')) {
                        $expiredScales[] = [
                            'name' => 'PEWS',
                            'last_value' => $patient['pews_score'] ?? '-',
                            'shift' => $patient['pews_shift'] ?? 'N/A',
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'braden')) {
                        $expiredScales[] = [
                            'name' => 'Braden',
                            'last_value' => $patient['braden_score'] ?? '-',
                            'shift' => $patient['braden_shift'] ?? 'N/A',
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'morse')) {
                        $expiredScales[] = [
                            'name' => 'Morse',
                            'last_value' => $patient['morse_score'] ?? '-',
                            'shift' => $patient['morse_shift'] ?? 'N/A',
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'pain')) {
                        $expiredScales[] = [
                            'name' => 'Dor',
                            'last_value' => $patient['pain_score'] ?? '-',
                            'shift' => $patient['pain_shift'] ?? 'N/A',
                        ];
                    }

                    if ($this->isScaleExpired($patient, 'vte')) {
                        $expiredScales[] = [
                            'name' => 'TEV',
                            'last_value' => $patient['vte_score'] ?? '-',
                            'shift' => $patient['vte_shift'] ?? 'N/A',
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
        return view('livewire.expired-scales-modal');
    }
}
