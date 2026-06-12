<?php

namespace App\Services\Handover\AiAnalysis;

class HandoverAnalysisPromptBuilder
{
    public function systemPrompt(): string
    {
        return 'Você é um especialista em gestão assistencial hospitalar e análise de comunicação clínica interprofissional. '
            .'Sua função é analisar registros de passagem de plantão de enfermagem do Hospital Santa Casa de Porto Alegre, '
            .'identificando padrões sistêmicos, lacunas informacionais e oportunidades de qualificação do cuidado. '
            .'Esses registros representam transferência de responsabilidade entre turnos — não são evoluções clínicas, prescrições ou laudos. '
            .'Escreva em português formal e técnico, com linguagem fluida e coesão argumentativa entre parágrafos. '
            .'Use títulos em Markdown (##) para estruturar seções. Dentro de cada seção, escreva em prosa densa — não em listas. '
            .'Jamais elabore rankings ou julgamentos de desempenho individual. '
            .'Fundamente cada afirmação nos dados fornecidos. Evite generalismos sem respaldo empírico.';
    }

    public function buildGeneralPrompt(array $dataset): string
    {
        $p = $dataset['period'];
        $sectors = collect($dataset['messages_by_sector'])
            ->map(fn ($s) => "{$s['name']}: {$s['messages']} mensagens, {$s['patients']} pacientes, {$s['professionals']} profissionais")
            ->implode("\n");

        $shifts = "Manhã: {$dataset['messages_by_shift']['M']} | Tarde: {$dataset['messages_by_shift']['T']} | Noite: {$dataset['messages_by_shift']['N']}";

        $professionals = collect($dataset['messages_by_professional'])
            ->take(20)
            ->map(fn ($prof) => "{$prof['name']}: {$prof['messages']} mensagens em {$prof['sessions']} sessões (turnos: {$prof['shifts']})")
            ->implode("\n");

        $enrichment = $this->formatEnrichment($dataset['enrichment'], $dataset['total_messages']);
        $topTerms = $this->formatTopTerms($dataset['top_terms']);
        $samples = implode("\n---\n", array_slice($dataset['message_samples'], 0, 40));

        return "ANÁLISE GERAL DE PASSAGEM DE PLANTÃO\n"
            ."Período: {$p['start']} a {$p['end']} ({$p['days']} dias)\n"
            ."Total: {$dataset['total_messages']} mensagens · {$dataset['total_patients']} pacientes · {$dataset['total_professionals']} profissionais\n"
            ."Tempo médio de anotação: {$dataset['avg_message_length']} caracteres por mensagem\n\n"
            ."SETORES PARTICIPANTES:\n{$sectors}\n\n"
            ."DISTRIBUIÇÃO POR TURNO:\n{$shifts}\n\n"
            ."PARTICIPAÇÃO POR PROFISSIONAL (sem ranking):\n{$professionals}\n\n"
            ."ENRIQUECIMENTO CLÍNICO (contagem de padrões detectados):\n{$enrichment}\n\n"
            ."TERMOS MAIS FREQUENTES:\n{$topTerms}\n\n"
            ."AMOSTRAS DE MENSAGENS (até 40):\n---\n{$samples}\n---\n\n"
            ."Redija uma análise técnica estruturada nas seguintes seções, cada uma introduzida por um título ## em Markdown. "
            ."Dentro de cada seção, escreva em prosa corrida — sem listas, sem marcadores, sem numeração:\n\n"
            ."## Panorama de Utilização\n"
            ."Interprete volume, evolução e distribuição entre turnos e setores. Contextualize o que os números revelam sobre a maturidade de adoção.\n\n"
            ."## Temas Clínicos Predominantes\n"
            ."Identifique e articule os grandes eixos temáticos presentes nas anotações, agrupando padrões assistenciais recorrentes.\n\n"
            ."## Pendências e Continuidade do Cuidado\n"
            ."Analise como exames, pareceres, altas e acompanhamentos são comunicados — e o que isso revela sobre a completude da transferência de informação entre turnos.\n\n"
            ."## Qualidade da Comunicação\n"
            ."Avalie objetividade, riqueza descritiva, uso de mensagens genéricas e lacunas informacionais observadas nas amostras.\n\n"
            ."## Oportunidades de Estruturação\n"
            ."Identifique informações que recorrentemente aparecem em forma livre mas poderiam ser capturadas em campos estruturados — com impacto direto na rastreabilidade clínica.\n\n"
            ."## Síntese e Recomendações\n"
            ."Articule os achados mais relevantes e proponha caminhos concretos de qualificação do processo, priorizando intervenções de maior impacto assistencial.\n\n"
            .'Seja analítico e preciso. Fundamente cada afirmação nos dados fornecidos. Evite o óbvio.';
    }

    public function buildSectorPrompt(array $dataset, string $sectorName): string
    {
        $p = $dataset['period'];
        $shifts = "Manhã: {$dataset['messages_by_shift']['M']} | Tarde: {$dataset['messages_by_shift']['T']} | Noite: {$dataset['messages_by_shift']['N']}";

        $professionals = collect($dataset['messages_by_professional'])
            ->take(15)
            ->map(fn ($prof) => "{$prof['name']}: {$prof['messages']} mensagens em {$prof['sessions']} sessões (turnos: {$prof['shifts']})")
            ->implode("\n");

        $enrichment = $this->formatEnrichment($dataset['enrichment'], $dataset['total_messages']);
        $topTerms = $this->formatTopTerms($dataset['top_terms']);
        $samples = implode("\n---\n", array_slice($dataset['message_samples'], 0, 40));

        return "ANÁLISE SETORIAL: {$sectorName}\n"
            ."Período: {$p['start']} a {$p['end']} ({$p['days']} dias)\n"
            ."Total: {$dataset['total_messages']} mensagens · {$dataset['total_patients']} pacientes · {$dataset['total_professionals']} profissionais\n"
            ."Tempo médio de anotação: {$dataset['avg_message_length']} caracteres por mensagem\n\n"
            ."DISTRIBUIÇÃO POR TURNO:\n{$shifts}\n\n"
            ."PARTICIPAÇÃO POR PROFISSIONAL (sem ranking, padrões documentais apenas):\n{$professionals}\n\n"
            ."ENRIQUECIMENTO CLÍNICO (contagem de padrões detectados):\n{$enrichment}\n\n"
            ."TERMOS MAIS FREQUENTES NO SETOR:\n{$topTerms}\n\n"
            ."AMOSTRAS DE MENSAGENS (até 40):\n---\n{$samples}\n---\n\n"
            ."Redija uma análise técnica do setor {$sectorName}, estruturada nas seções abaixo com títulos ## em Markdown. "
            ."Escreva em prosa coesa — sem listas, sem marcadores, sem numeração:\n\n"
            ."## Perfil do Setor\n"
            ."Interprete volume, frequência de uso e distribuição por turno. O que os dados revelam sobre a dinâmica assistencial deste setor?\n\n"
            ."## Temas Clínicos Predominantes\n"
            ."Articule os principais eixos temáticos das anotações — cuidados mais documentados, focos assistenciais recorrentes.\n\n"
            ."## Pendências e Transferência de Informação\n"
            ."Como exames, pareceres, dispositivos e altas aparecem nas comunicações? Avalie a completude da transferência interturno.\n\n"
            ."## Qualidade e Padrões Documentais\n"
            ."Analise objetividade, riqueza descritiva, frases genéricas e repetições observadas nas amostras do setor.\n\n"
            ."## Oportunidades de Qualificação\n"
            ."Identifique melhorias concretas para o fluxo comunicacional deste setor — campos estruturáveis, lacunas informacionais, automações possíveis.\n\n"
            ."## Síntese\n"
            ."Articule os achados mais relevantes em forma de aprendizado acionável sobre a prática comunicacional deste setor.\n\n"
            .'Base tudo nos dados fornecidos. Seja específico e analítico.';
    }

    // ── Formatação interna do dataset para o prompt ────────────────────────────

    private function formatEnrichment(array $enrichment, int $total): string
    {
        if (empty($enrichment)) {
            return 'Nenhum padrão detectado.';
        }

        $labels = [
            'pending_actions'  => 'Pendências',
            'exams'            => 'Exames',
            'referrals'        => 'Pareceres/Interconsultas',
            'discharges'       => 'Altas/Previsão de alta',
            'recommendations'  => 'Recomendações/Continuidade',
            'pain'             => 'Dor',
            'fall_risk'        => 'Risco de queda',
            'isolation'        => 'Isolamento',
            'wound_care'       => 'Curativos/Feridas',
            'devices'          => 'Dispositivos (cateter, sonda, etc.)',
            'generic_messages' => 'Mensagens genéricas (estável, sem alteração)',
        ];

        $lines = [];
        foreach ($enrichment as $key => $data) {
            $label = $labels[$key] ?? $key;
            $lines[] = "- {$label}: {$data['count']} mensagens ({$data['pct']}% do total)";
        }

        return implode("\n", $lines);
    }

    private function formatTopTerms(array $terms): string
    {
        if (empty($terms)) {
            return 'Nenhum termo relevante extraído.';
        }

        return collect($terms)
            ->take(20)
            ->map(fn ($count, $term) => "{$term} ({$count}×)")
            ->implode(', ');
    }
}
