<?php

namespace App\Enums\Huddle;

/**
 * Perguntas do checklist do Huddle de Gestão de Altas (metodologia Red2Green).
 *
 * Cada item é respondido por paciente, por dia, e gera um sinal Red ou Green.
 * A polaridade muda por pergunta: em algumas "Sim" é Green (critérios atendidos),
 * em outras "Sim" é Red (exames pendentes). O método greenAnswer() diz qual
 * resposta representa o dia produtivo (Green) para orientar a tela.
 *
 * O gate de previsão de alta em 72h não é um item daqui — ele define o status do
 * paciente (huddle/round) no HuddlePatientDay, antes do checklist.
 */
enum HuddleChecklistItem: string
{
    case CriteriosClinicos = 'criterios_clinicos';
    case ExamesLaudo = 'exames_laudo';
    case Procedimentos = 'procedimentos';
    case Terapias = 'terapias';
    case Consultorias = 'consultorias';
    case OrientacaoAlta = 'orientacao_alta';
    case Transporte = 'transporte';

    /**
     * Texto da pergunta exibido na tela.
     */
    public function label(): string
    {
        return match ($this) {
            self::CriteriosClinicos => 'Critérios clínicos estipulados para a alta atendidos?',
            self::ExamesLaudo => 'Exames pendentes ou aguardando laudo?',
            self::Procedimentos => 'Procedimentos pendentes?',
            self::Terapias => 'Terapias pendentes?',
            self::Consultorias => 'Consultorias pendentes?',
            self::OrientacaoAlta => 'Paciente e familiar orientados sobre a previsão de alta?',
            self::Transporte => 'Transporte organizado?',
        };
    }

    /**
     * Qual resposta ('sim' ou 'nao') representa o dia produtivo (Green).
     * A tela usa isso para sugerir o sinal ao selecionar a resposta.
     */
    public function greenAnswer(): string
    {
        return match ($this) {
            self::CriteriosClinicos,
            self::OrientacaoAlta,
            self::Transporte => 'sim',

            self::ExamesLaudo,
            self::Procedimentos,
            self::Terapias,
            self::Consultorias => 'nao',
        };
    }

    /**
     * Orientação de ação quando o item fica Red (mostrada como ajuda na tela).
     */
    public function redAction(): string
    {
        return match ($this) {
            self::CriteriosClinicos => 'Registrar item pendente e responsável médico com prazo de devolutiva; alterar a previsão de alta se necessário.',
            self::ExamesLaudo => 'Acionar equipe do CDI, laboratório e/ou patologia para priorização.',
            self::Procedimentos => 'Acionar equipe e bloco cirúrgico para priorização.',
            self::Terapias => 'Se em terapia, alinhar necessidade com o médico (dia produtivo = Green, com atenção). Terapia prescrita e não realizada = Red.',
            self::Consultorias => 'Conversar com a equipe responsável pela consultoria para priorização.',
            self::OrientacaoAlta => 'Definir prazo para a equipe responsável conversar com paciente/família e registrar.',
            self::Transporte => 'Definir prazo para a equipe responsável alinhar o transporte.',
        };
    }

    /**
     * Indica se este item costuma exigir responsável e prazo quando marcado Red.
     */
    public function requiresFollowUp(): bool
    {
        return match ($this) {
            self::CriteriosClinicos,
            self::OrientacaoAlta,
            self::Transporte => true,
            default => false,
        };
    }
}
