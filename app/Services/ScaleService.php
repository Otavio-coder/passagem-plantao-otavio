<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service centralizado para processamento de escalas assistenciais
 * Padroniza retorno com apenas: score, timestamp, classification e styling
 */
class ScaleService
{
    /**
     * Processa dados brutos de escalas em formato padronizado
     * 
     * @param object $scalesData Dados brutos do repository
     * @param bool $isPediatric Se é paciente pediátrico
     * @return array
     */
    public function processScalesData($scalesData, $isPediatric = false)
    {
        if (!$scalesData) {
            return $this->getEmptyScalesData($isPediatric);
        }

        $scales = [];

        // MEWS (adultos) ou PEWS (pediátricos)
        if (!$isPediatric) {
            $scales['mews'] = $this->processMewsScale($scalesData);
        } else {
            $scales['pews'] = $this->processPewsScale($scalesData);
        }

        // Escalas comuns
        $scales['braden'] = $this->processBradenScale($scalesData);
        $scales['morse'] = $this->processMorseScale($scalesData);
        $scales['dor'] = $this->processPainScale($scalesData);
        $scales['tev'] = $this->processTevScale($scalesData);

        return $scales;
    }

    /**
     * Processa escala MEWS
     */
    private function processMewsScale($data)
    {
        return [
            'score' => $this->extractScore($data->mews_atual ?? null),
            'timestamp' => $this->formatTimestamp($data->mews_data ?? null),
            'classification' => $this->getMewsClassification($data->mews_atual ?? null),
            'styling' => $this->getMewsStyling($data->mews_atual ?? null),
            'period' => 'turno',
            'increased' => $this->hasIncreased($data->mews_atual ?? null, $data->mews_anterior ?? null),
            'needs_assessment' => $this->needsAssessment($data->mews_data ?? null, 'turno')
        ];
    }

    /**
     * Processa escala PEWS
     */
    private function processPewsScale($data)
    {
        return [
            'score' => $this->extractScore($data->pews_atual ?? null),
            'timestamp' => $this->formatTimestamp($data->pews_data ?? null),
            'classification' => $this->getPewsClassification($data->pews_atual ?? null),
            'styling' => $this->getPewsStyling($data->pews_atual ?? null),
            'period' => 'turno',
            'increased' => $this->hasIncreased($data->pews_atual ?? null, $data->pews_anterior ?? null),
            'needs_assessment' => $this->needsAssessment($data->pews_data ?? null, 'turno')
        ];
    }

    /**
     * Processa escala Braden
     */
    private function processBradenScale($data)
    {
        return [
            'score' => $this->extractScore($data->braden_atual ?? null),
            'timestamp' => $this->formatTimestamp($data->braden_data ?? null),
            'classification' => $this->getBradenClassification($data->braden_atual ?? null),
            'styling' => $this->getBradenStyling($data->braden_atual ?? null),
            'period' => '24h',
            'increased' => $this->hasIncreased($data->braden_atual ?? null, $data->braden_anterior ?? null),
            'needs_assessment' => $this->needsAssessment($data->braden_data ?? null, '24h')
        ];
    }

    /**
     * Processa escala Morse
     */
    private function processMorseScale($data)
    {
        return [
            'score' => $this->extractScore($data->morse_atual ?? null),
            'timestamp' => $this->formatTimestamp($data->morse_data ?? null),
            'classification' => $this->getMorseClassification($data->morse_atual ?? null),
            'styling' => $this->getMorseStyling($data->morse_atual ?? null),
            'period' => '24h',
            'increased' => $this->hasIncreased($data->morse_atual ?? null, $data->morse_anterior ?? null),
            'needs_assessment' => $this->needsAssessment($data->morse_data ?? null, '24h')
        ];
    }

    /**
     * Processa escala de Dor
     */
    private function processPainScale($data)
    {
        return [
            'score' => $this->extractScore($data->dor_atual ?? null),
            'timestamp' => $this->formatTimestamp($data->dor_data ?? null),
            'classification' => $this->getPainClassification($data->dor_atual ?? null),
            'styling' => $this->getPainStyling($data->dor_atual ?? null),
            'period' => 'turno',
            'increased' => $this->hasIncreased($data->dor_atual ?? null, $data->dor_anterior ?? null),
            'needs_assessment' => $this->needsAssessment($data->dor_data ?? null, 'turno')
        ];
    }

    /**
     * Processa escala TEV
     */
    private function processTevScale($data)
    {
        return [
            'score' => $this->extractScore($data->tev_atual ?? null),
            'timestamp' => $this->formatTimestamp($data->tev_data ?? null),
            'classification' => $this->getTevClassification($data->tev_atual ?? null),
            'styling' => $this->getTevStyling($data->tev_atual ?? null),
            'period' => '24h',
            'increased' => $this->hasIncreased($data->tev_atual ?? null, $data->tev_anterior ?? null),
            'needs_assessment' => $this->needsAssessment($data->tev_data ?? null, '24h')
        ];
    }

    /**
     * Extrai score numérico de diferentes formatos
     */
    private function extractScore($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Se já é número
        if (is_numeric($value)) {
            return (int) $value;
        }

        // Extrai número de string
        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Formata timestamp para exibição
     */
    private function formatTimestamp($timestamp)
    {
        if (!$timestamp) {
            return null;
        }

        try {
            return Carbon::parse($timestamp)->format('d/m H:i');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verifica se score aumentou comparado ao anterior
     */
    private function hasIncreased($current, $previous)
    {
        $currentScore = $this->extractScore($current);
        $previousScore = $this->extractScore($previous);

        if ($currentScore === null || $previousScore === null) {
            return false;
        }

        return $currentScore > $previousScore;
    }

    /**
     * Verifica se precisa de nova avaliação baseado no tempo
     */
    private function needsAssessment($lastAssessment, $period)
    {
        if (!$lastAssessment) {
            return true;
        }

        try {
            $lastTime = Carbon::parse($lastAssessment);
            $now = now();
            
            if ($period === 'turno') {
                // Verifica se passou do turno atual (6 horas)
                return $lastTime->diffInHours($now) >= 6;
            } else {
                // Verifica se passou de 24 horas
                return $lastTime->diffInHours($now) >= 24;
            }
        } catch (\Exception $e) {
            return true;
        }
    }

    // CLASSIFICAÇÕES DE RISCO

    private function getMewsClassification($score)
    {
        $num = $this->extractScore($score);
        if ($num === null) return null;
        
        if ($num >= 5) return 'Crítico';
        if ($num >= 3) return 'Alerta';
        return 'Normal';
    }

    private function getPewsClassification($score)
    {
        $num = $this->extractScore($score);
        if ($num === null) return null;
        
        if ($num >= 7) return 'Crítico';
        if ($num >= 4) return 'Alerta';
        return 'Normal';
    }

    private function getBradenClassification($score)
    {
        $num = $this->extractScore($score);
        if ($num === null) return null;
        
        if ($num <= 12) return 'Alto Risco';
        if ($num <= 14) return 'Risco Moderado';
        return 'Baixo Risco';
    }

    private function getMorseClassification($score)
    {
        $num = $this->extractScore($score);
        if ($num === null) return null;
        
        if ($num >= 45) return 'Alto Risco';
        if ($num >= 25) return 'Risco Moderado';
        return 'Baixo Risco';
    }

    private function getPainClassification($score)
    {
        $num = $this->extractScore($score);
        if ($num === null) return null;
        
        if ($num == 0) return 'Sem Dor';
        if ($num >= 7) return 'Dor Intensa';
        if ($num >= 4) return 'Dor Moderada';
        return 'Dor Leve';
    }

    private function getTevClassification($score)
    {
        $num = $this->extractScore($score);
        if ($num === null) return null;
        
        if ($num >= 5) return 'Alto Risco';
        if ($num >= 2) return 'Risco Moderado';
        return 'Baixo Risco';
    }

    // ESTILOS PARA CORES

    private function getMewsStyling($score)
    {
        return function_exists('getMewsRiskStyling') 
            ? getMewsRiskStyling($this->extractScore($score), false)
            : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
    }

    private function getPewsStyling($score)
    {
        return function_exists('getPewsRiskStyling') 
            ? getPewsRiskStyling($this->extractScore($score), false)
            : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
    }

    private function getBradenStyling($score)
    {
        return function_exists('getBradenRiskStyling') 
            ? getBradenRiskStyling($this->extractScore($score), false)
            : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
    }

    private function getMorseStyling($score)
    {
        return function_exists('getMorseRiskStyling') 
            ? getMorseRiskStyling($this->extractScore($score), false)
            : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
    }

    private function getPainStyling($score)
    {
        return function_exists('getPainRiskStyling') 
            ? getPainRiskStyling($this->extractScore($score), false)
            : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
    }

    private function getTevStyling($score)
    {
        return function_exists('getTevRiskStyling') 
            ? getTevRiskStyling($this->extractScore($score), false)
            : ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
    }

    /**
     * Retorna estrutura vazia quando não há dados
     */
    private function getEmptyScalesData($isPediatric = false)
    {
        $emptyScale = [
            'score' => null,
            'timestamp' => null,
            'classification' => null,
            'styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-400'],
            'increased' => false,
            'needs_assessment' => true
        ];

        $scales = [
            'braden' => array_merge($emptyScale, ['period' => '24h']),
            'morse' => array_merge($emptyScale, ['period' => '24h']),
            'dor' => array_merge($emptyScale, ['period' => 'turno']),
            'tev' => array_merge($emptyScale, ['period' => '24h'])
        ];

        if (!$isPediatric) {
            $scales['mews'] = array_merge($emptyScale, ['period' => 'turno']);
        } else {
            $scales['pews'] = array_merge($emptyScale, ['period' => 'turno']);
        }

        return $scales;
    }
}