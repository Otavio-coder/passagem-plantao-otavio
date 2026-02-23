<?php
// File: `app/Repositories/EMR/PatientScalesRepository.php`
namespace App\Repositories\EMR;

use Illuminate\Support\Facades\Log;
use App\Models\EMR\Scales\{Mews, Pews, Braden, Morse, VteThrombosis, Pain};

class PatientScalesRepository
{
    protected $connection = 'tasy';
    protected const CHUNK_SIZE = 200;

    public function getPatientsScalesUnified(array $attendanceNumbers, array $isNewPatientMap = []): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $result = [];
        $chunks = array_chunk(array_values($attendanceNumbers), self::CHUNK_SIZE);

        foreach ($chunks as $chunk) {
            try {
                // Busca todas as escalas
                $mewsRows = Mews::query()
                    ->whereIn('nr_atendimento', $chunk)
                    ->whereNotNull('dt_liberacao')
                    ->whereNull('dt_inativacao')
                    ->whereNotNull('qt_pontuacao')
                    ->orderByDesc('dt_avaliacao')
                    ->get()
                    ->groupBy('nr_atendimento');

                $pewsRows = Pews::query()
                    ->whereIn('nr_atendimento', $chunk)
                    ->whereNotNull('dt_liberacao')
                    ->whereNull('dt_inativacao')
                    ->whereNotNull('qt_pontuacao')
                    ->orderByDesc('dt_avaliacao')
                    ->get()
                    ->groupBy('nr_atendimento');

                $bradenRows = Braden::query()
                    ->whereIn('nr_atendimento', $chunk)
                    ->whereNotNull('dt_liberacao')
                    ->whereNull('dt_inativacao')
                    ->whereNotNull('qt_ponto')
                    ->orderByDesc('dt_avaliacao')
                    ->get()
                    ->groupBy('nr_atendimento');

                $morseRows = Morse::query()
                    ->whereIn('nr_atendimento', $chunk)
                    ->whereNotNull('dt_liberacao')
                    ->whereNull('dt_inativacao')
                    ->whereNotNull('qt_pontuacao')
                    ->orderByDesc('dt_avaliacao')
                    ->get()
                    ->groupBy('nr_atendimento');

                $painRows = Pain::query()
                    ->whereIn('nr_atendimento', $chunk)
                    ->whereNotNull('dt_liberacao')
                    ->whereNull('dt_inativacao')
                    ->whereNotNull('qt_escala_dor')
                    ->orderByDesc('dt_sinal_vital')
                    ->get()
                    ->groupBy('nr_atendimento');

                $vteRows = VteThrombosis::query()
                    ->whereIn('nr_atendimento', $chunk)
                    ->whereNotNull('dt_liberacao')
                    ->whereNull('dt_inativacao')
                    ->whereNotNull('qt_pontuacao')
                    ->orderByDesc('dt_avaliacao')
                    ->get()
                    ->groupBy('nr_atendimento');

                // Processa cada attendance
                foreach ($chunk as $attendance) {
                    $isNew = (bool)($isNewPatientMap[$attendance] ?? false);

                    // Busca current e previous para cada escala
                    [$mewsCurrent, $mewsPrev] = $this->pickCurrentAndPrevious($mewsRows, $attendance);
                    [$pewsCurrent, $pewsPrev] = $this->pickCurrentAndPrevious($pewsRows, $attendance);
                    [$bradenCurrent, $bradenPrev] = $this->pickCurrentAndPrevious($bradenRows, $attendance);
                    [$morseCurrent, $morsePrev] = $this->pickCurrentAndPrevious($morseRows, $attendance);
                    [$painCurrent, $painPrev] = $this->pickCurrentAndPrevious($painRows, $attendance);
                    [$vteCurrent, $vtePrev]   = $this->pickCurrentAndPrevious($vteRows, $attendance);

                    // Build structures
                    $result[$attendance] = [
                        'mews'   => Mews::buildStructure($mewsCurrent, $mewsPrev, 'turno', $isNew),
                        'pews'   => Pews::buildStructure($pewsCurrent, $pewsPrev, 'turno', $isNew),
                        'braden' => Braden::buildStructure($bradenCurrent, $bradenPrev, '24h', $isNew),
                        'morse'  => Morse::buildStructure($morseCurrent, $morsePrev, '24h', $isNew),
                        'pain'   => Pain::buildStructure($painCurrent, $painPrev, 'turno', $isNew),
                        'vte'    => VteThrombosis::buildStructure($vteCurrent, $vtePrev, '24h', $isNew),
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('PatientScalesRepository::getPatientsScalesUnified failed', [
                    'error' => $e->getMessage(),
                    'chunk_sample' => array_slice($chunk, 0, 5),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $result;
    }

    /**
     * Extrai current e previous de uma collection agrupada
     * Retorna o registro mais recente como current e o segundo mais recente como previous
     */
    private function pickCurrentAndPrevious($grouped, $attendanceKey): array
    {
        if (!isset($grouped[$attendanceKey])) {
            return [null, null];
        }

        $collection = $grouped[$attendanceKey]->values();
        $current = $collection->get(0) ?? null;

        if (!$current) {
            return [null, null];
        }

        $previous = null;

        // Busca o primeiro registro que seja anterior ao current
        for ($i = 1; $i < $collection->count(); $i++) {
            $candidate = $collection->get($i);

            if ($this->isBeforeCurrent($current, $candidate)) {
                $previous = $candidate;
                break;
            }
        }

        return [$current, $previous];
    }

    /**
     * Verifica se candidate é anterior ao current baseado na data
     */
    private function isBeforeCurrent($current, $candidate): bool
    {
        if (!$current || !$candidate) {
            return false;
        }

        $dateCol = get_class($candidate) === Pain::class
            ? 'dt_sinal_vital'
            : 'dt_avaliacao';

        $currentTs = $current->{$dateCol} ?? null;
        $candidateTs = $candidate->{$dateCol} ?? null;

        if (!$currentTs || !$candidateTs) {
            return false;
        }

        try {
            $currentCarbon = \Carbon\Carbon::parse($currentTs);
            $candidateCarbon = \Carbon\Carbon::parse($candidateTs);

            return $candidateCarbon < $currentCarbon;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Backwards compatibility helper
     */
    public function getPatientScalesUnified(int $attendanceNumber, bool $isNewPatient = false): ?array
    {
        if (!$attendanceNumber) {
            return null;
        }

        $map = $this->getPatientsScalesUnified(
            [$attendanceNumber],
            [$attendanceNumber => $isNewPatient]
        );

        return $map[$attendanceNumber] ?? null;
    }
}
