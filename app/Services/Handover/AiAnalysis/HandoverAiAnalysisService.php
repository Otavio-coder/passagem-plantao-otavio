<?php

namespace App\Services\Handover\AiAnalysis;

use App\Models\HandoverAiAnalysis;
use Carbon\Carbon;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

class HandoverAiAnalysisService
{
    private const PROMPT_VERSION = 'v1';

    public function __construct(
        private readonly HandoverAnalysisDatasetService $datasetService,
        private readonly HandoverAnalysisPromptBuilder $promptBuilder
    ) {}

    /**
     * Executa a análise: prepara dataset, constrói prompt, chama IA, persiste resultado.
     * Atualiza o model em todas as etapas.
     */
    public function analyze(HandoverAiAnalysis $analysis): HandoverAiAnalysis
    {
        try {
            $analysis->update(['status' => 'processing']);

            $start = Carbon::parse($analysis->period_start);
            $end = Carbon::parse($analysis->period_end);

            $dataset = $this->datasetService->prepare($start, $end, $analysis->sector_id);

            $analysis->update([
                'total_messages'      => $dataset['total_messages'],
                'total_patients'      => $dataset['total_patients'],
                'total_professionals' => $dataset['total_professionals'],
                'dataset_metadata'    => $dataset,
            ]);

            if ($dataset['total_messages'] === 0) {
                $analysis->update([
                    'status'         => 'completed',
                    'analysis_text'  => 'Nenhuma mensagem encontrada no período e filtros selecionados. Não é possível gerar análise.',
                    'generated_at'   => now(),
                    'prompt_version' => self::PROMPT_VERSION,
                ]);

                return $analysis;
            }

            $prompt = $analysis->analysis_type === 'sector'
                ? $this->promptBuilder->buildSectorPrompt($dataset, $analysis->sector_name ?? 'Setor')
                : $this->promptBuilder->buildGeneralPrompt($dataset);

            $analysis->update([
                'prompt_used'    => $prompt,
                'prompt_version' => self::PROMPT_VERSION,
            ]);

            $response = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4o-mini')
                ->withSystemPrompt($this->promptBuilder->systemPrompt())
                ->withPrompt($prompt)
                ->asText();

            $analysis->update([
                'status'        => 'completed',
                'analysis_text' => $response->text,
                'generated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            $analysis->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $analysis->fresh();
    }
}
