<?php

namespace App\Services\Handover\AiAnalysis;

use App\Services\ChatAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

class HandoverTermsAnalysisService
{
    /**
     * Gera análise textual rápida dos termos e amostras de mensagens do período.
     * Não persiste resultado — uso efêmero no Panorama.
     */
    public function analyze(
        array $topTerms,
        ?Carbon $since,
        ?int $sectorId,
        int $sessionCount,
        int $professionalCount,
        string $sectorNames
    ): string {
        $analytics = app(ChatAnalyticsService::class);

        $liveSamples = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->orderByDesc('created_at')
            ->limit(120)
            ->pluck('content')
            ->all();

        $liveCount = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->count();

        $archiveBuckets = $analytics->getCharBuckets($since, $sectorId);
        $archiveTags = $analytics->getContentTags($since, $sectorId);
        $archiveTotal = $archiveBuckets['message_count'] ?? 0;
        $totalMessages = $liveCount + $archiveTotal;

        $termsText = collect($topTerms)
            ->take(20)
            ->map(fn ($count, $term) => "{$term} ({$count}×)")
            ->implode(', ');

        $msgSample = implode("\n---\n", array_slice($liveSamples, 0, 60));

        $archiveTagsText = collect($archiveTags)
            ->map(fn ($c, $t) => "{$t}: {$c}")
            ->implode(', ');

        $systemPrompt = 'Você é especialista em gestão assistencial hospitalar e análise de comunicação clínica. '
            .'Analise registros de passagem de plantão de enfermagem do Hospital Santa Casa de Porto Alegre. '
            .'Escreva em português clínico direto. Frases curtas e declarativas. Sem hedging. '
            .'Use títulos ## em Markdown para estruturar as seções. '
            .'Dados → interpretação → implicação clínica. Sem conclusões óbvias.';

        $prompt = "Contexto: {$totalMessages} anotações totais ({$liveCount} recentes + {$archiveTotal} históricas), "
            ."{$sessionCount} sessões, {$professionalCount} plantonistas, setores: {$sectorNames}.\n\n"
            ."Termos mais frequentes (anotações recentes): {$termsText}\n\n"
            .($archiveTagsText ? "Categorias no histórico completo: {$archiveTagsText}\n\n" : '')
            ."Amostra de anotações recentes (até 60):\n---\n{$msgSample}\n---\n\n"
            ."Redija análise técnica nas seções abaixo (títulos ## em Markdown, prosa direta e declarativa):\n\n"
            ."## Eixos Temáticos\n"
            ."Agrupe os termos em temas clínicos. O que revelam sobre focos assistenciais documentados.\n\n"
            ."## Padrões de Comunicação\n"
            ."Como os plantonistas estruturam as anotações. Foco clínico, procedimental ou administrativo.\n\n"
            ."## Qualidade e Lacunas\n"
            ."Completude, objetividade, padrões de repetição ou omissão.\n\n"
            ."## Insight Diagnóstico\n"
            ."O que esses dados revelam sobre maturidade da comunicação assistencial.\n\n"
            .'Preciso e direto. Sem o óbvio. Máximo 250 palavras.';

        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($prompt)
            ->asText();

        return $response->text;
    }

    /**
     * Gera análise do padrão de documentação de um plantonista individual.
     * Foco em temas e qualidade da escrita — não avalia produtividade.
     */
    public function analyzeNurseProfile(array $nurseDetail, array $topTerms, ?Carbon $since): string
    {
        $name = $nurseDetail['name'] ?? 'Plantonista';
        $summary = $nurseDetail['summary'] ?? [];
        $sessions = $summary['total_sessions'] ?? 0;
        $avgMsgs = $summary['avg_messages_per_session'] ?? 0;
        $avgChars = $summary['avg_chars'] ?? 0;
        $sizeLabel = $summary['size_label'] ?? '';
        $avgBeds = $summary['avg_beds'] ?? 0;
        $shifts = $nurseDetail['shift_distribution'] ?? ['M' => 0, 'T' => 0, 'N' => 0];
        $userId = (int) $nurseDetail['user_id'];

        $messageSamples = DB::table('chat_messages')
            ->where('user_id', $userId)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->orderByDesc('created_at')
            ->limit(80)
            ->pluck('content')
            ->all();

        $termsText = collect($topTerms)
            ->take(15)
            ->map(fn ($count, $term) => "{$term} ({$count}×)")
            ->implode(', ');

        $msgSample = implode("\n---\n", array_slice($messageSamples, 0, 40));

        $shiftText = "Manhã: {$shifts['M']}, Tarde: {$shifts['T']}, Noite: {$shifts['N']}";

        $systemPrompt = 'Você é especialista em análise de documentação clínica hospitalar. '
            .'Analise padrões de escrita de registros de passagem de plantão de enfermagem de um profissional. '
            .'Português clínico direto. Frases declarativas. Sem hedging. Sem listas ou marcadores. '
            .'Use títulos ## em Markdown. Jamais avalie produtividade ou compare com outros profissionais. '
            .'Fundamente cada afirmação nas anotações fornecidas.';

        $prompt = "Plantonista: {$name} | Sessões: {$sessions} | Leitos/sessão: {$avgBeds} | "
            ."Anotações/sessão: {$avgMsgs} | Extensão média: {$avgChars}ch ({$sizeLabel})\n"
            ."Turnos: M:{$shifts['M']} T:{$shifts['T']} N:{$shifts['N']}\n\n"
            ."Termos recorrentes: {$termsText}\n\n"
            ."Amostra de anotações (até 40):\n---\n{$msgSample}\n---\n\n"
            ."Análise técnica do perfil documental (títulos ## em Markdown, prosa direta):\n\n"
            ."## Eixos Temáticos Documentados\n"
            ."Focos clínicos predominantes. Especialização ou amplitude temática.\n\n"
            ."## Características da Escrita\n"
            ."Objetividade, densidade informacional e riqueza descritiva das anotações.\n\n"
            ."## Oportunidade de Qualificação\n"
            ."Uma oportunidade concreta e específica de tornar as passagens mais informativas para o próximo turno.\n\n"
            .'Máximo 200 palavras. Baseie-se exclusivamente nas anotações. Nunca avalie produtividade.';

        return $this->callPrism($systemPrompt, $prompt);
    }

    public function analyzeShiftStats(array $shiftStats, string $periodLabel): string
    {
        $systemPrompt = self::clinicalSystemPrompt();

        $total = $shiftStats['total'] ?? 0;
        $prompt = "Período: {$periodLabel}. Total: {$total} sessões.\n"
            ."Manhã: {$shiftStats['M']} ({$shiftStats['pct_M']}%) | "
            ."Tarde: {$shiftStats['T']} ({$shiftStats['pct_T']}%) | "
            ."Noite: {$shiftStats['N']} ({$shiftStats['pct_N']}%)\n\n"
            ."Interprete a distribuição: desequilíbrios entre turnos, riscos concretos à continuidade assistencial, "
            ."implicações para adesão ao protocolo de passagem. "
            .'Frases declarativas. Sem hedging. Máximo 100 palavras.';

        return $this->callPrism($systemPrompt, $prompt);
    }

    public function analyzeHourly(array $heatmap, string $periodLabel): string
    {
        $systemPrompt = self::clinicalSystemPrompt();

        $heatmapText = '';
        foreach ($heatmap as $row) {
            $peakCells = collect($row['cells'])
                ->sortByDesc('count')
                ->take(3)
                ->map(fn ($c) => "{$c['hour']}h: {$c['count']} ({$c['pct']}%)")
                ->implode(', ');
            $heatmapText .= "{$row['label']}: {$peakCells}\n";
        }

        $prompt = "Período: {$periodLabel}.\nVolume de anotações por hora:\n{$heatmapText}\n"
            ."Identifique picos de registro, lacunas intratrabalho e timing crítico nas transições de turno. "
            ."O que os horários revelam sobre quando as passagens efetivamente ocorrem. "
            .'Frases declarativas. Sem hedging. Máximo 100 palavras.';

        return $this->callPrism($systemPrompt, $prompt);
    }

    public function analyzeContent(array $contentClassification, string $periodLabel): string
    {
        $systemPrompt = self::clinicalSystemPrompt();

        $categoriesText = collect($contentClassification)
            ->map(fn ($data, $cat) => "{$cat}: {$data['pct']}% ({$data['count']})")
            ->implode(' | ');

        $prompt = "Período: {$periodLabel}.\nCategorias: {$categoriesText}\n\n"
            ."O que a distribuição revela sobre foco assistencial da equipe. "
            ."Quais dimensões estão sub-representadas e o que isso implica para a qualidade da comunicação entre turnos. "
            .'Frases declarativas. Sem hedging. Máximo 100 palavras.';

        return $this->callPrism($systemPrompt, $prompt);
    }

    public function analyzeCharDistribution(array $charDistribution, string $periodLabel): string
    {
        $systemPrompt = self::clinicalSystemPrompt();

        $curtas = $charDistribution['buckets']['curtas'] ?? [];
        $adequadas = $charDistribution['buckets']['adequadas'] ?? [];
        $longas = $charDistribution['buckets']['longas'] ?? [];

        $prompt = "Período: {$periodLabel}. Total: {$charDistribution['total']} anotações. "
            ."Média: {$charDistribution['avg']} ch ({$charDistribution['size_label']}).\n"
            ."Curtas <60ch: {$curtas['pct']}% | Adequadas 60-200ch: {$adequadas['pct']}% | Longas >200ch: {$longas['pct']}%\n\n"
            ."O que essa distribuição revela sobre a densidade informacional das passagens. "
            ."Implicações concretas para continuidade assistencial. "
            .'Frases declarativas. Sem hedging. Máximo 100 palavras.';

        return $this->callPrism($systemPrompt, $prompt);
    }

    public function analyzeActivity(array $annotationsByDay, string $periodLabel): string
    {
        $systemPrompt = self::clinicalSystemPrompt();

        $values = array_column($annotationsByDay, 'value');
        $daysWithData = count(array_filter($values, fn ($v) => $v > 0));
        $totalAnnotations = array_sum($values);
        $totalDays = count($annotationsByDay);
        $avgPerDay = $totalDays > 0 ? round($totalAnnotations / $totalDays, 1) : 0;
        $maxValue = ! empty($values) ? max($values) : 0;
        $maxDay = '';
        foreach ($annotationsByDay as $entry) {
            if ($entry['value'] === $maxValue) {
                $maxDay = $entry['date'];
                break;
            }
        }

        $prompt = "Período: {$periodLabel}. {$totalDays} dias, {$daysWithData} com registro. "
            ."Total: {$totalAnnotations} anotações. Média: {$avgPerDay}/dia. Pico: {$maxValue} em {$maxDay}.\n\n"
            ."Identifique padrões de regularidade, lacunas temporais e tendências. "
            ."O que esses padrões revelam sobre consistência da comunicação assistencial. "
            .'Frases declarativas. Sem hedging. Máximo 100 palavras.';

        return $this->callPrism($systemPrompt, $prompt);
    }

    private static function clinicalSystemPrompt(): string
    {
        return 'Você é especialista em gestão assistencial hospitalar. '
            .'Analise dados de passagem de plantão de enfermagem do Hospital Santa Casa de Porto Alegre. '
            .'ESTILO OBRIGATÓRIO: português clínico direto. Frases curtas. Afirmações declarativas. '
            .'PROIBIDO: "pode", "sugere", "indica", "revela", "possível", "potencial", "tende a". '
            .'USE: fatos diretos — "X representa risco Y", "Turno N tem cobertura insuficiente", "A lacuna em Z compromete a continuidade". '
            .'Sem conclusões óbvias. Sem listas ou marcadores. Dados → fato → implicação clínica direta.';
    }

    private function callPrism(string $systemPrompt, string $prompt): string
    {
        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($prompt)
            ->asText();

        return $response->text;
    }
}
