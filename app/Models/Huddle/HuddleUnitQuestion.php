<?php

namespace App\Models\Huddle;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pergunta/campo do formulário do Round Unidade (Huddle de Segurança por unidade).
 *
 * Cada registro define uma pergunta que aparece no modal de avaliação da unidade.
 * O `field_key` é a chave que mapeia para a coluna correspondente na tabela
 * `huddle_safety_assessments`, onde a resposta é de fato gravada.
 *
 * Tipos de campo:
 *   - number: input numérico (ex: Altas Previstas)
 *   - boolean: botões Sim/Não (ex: Paciente grave sem leito?)
 *   - select: seleção de opções definidas em `options` (ex: Classificação da unidade)
 *   - text: textarea livre (ex: Justificativa, Medidas imediatas)
 */
class HuddleUnitQuestion extends Model
{
    protected $table = 'huddle_unit_questions';

    protected $fillable = [
        'axis',
        'axis_label',
        'field_key',
        'label',
        'field_type',
        'options',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'axis' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('axis')->orderBy('sort_order');
    }

    /**
     * Agrupa as perguntas ativas por eixo, já ordenadas.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, static>>
     */
    public static function activeGroupedByAxis(): \Illuminate\Support\Collection
    {
        return static::active()
            ->ordered()
            ->get()
            ->groupBy('axis');
    }
}
