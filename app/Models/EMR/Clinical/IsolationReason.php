<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model para MOTIVO_ISOLAMENTO (Motivos de Isolamento)
 *
 * Representa os possíveis motivos para colocar um paciente em isolamento.
 * Exemplos: COVID-19, Bactéria Multirresistente, Varicela, etc.
 */
class IsolationReason extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.MOTIVO_ISOLAMENTO';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    // ========== RELAÇÕES ==========

    /**
     * Isolamentos que usam este motivo
     */
    public function isolations(): HasMany
    {
        return $this->hasMany(Isolation::class, 'nr_seq_motivo_isol', 'nr_sequencia');
    }

    // ========== SCOPES ==========

    /**
     * Motivos ativos
     */
    public function scopeActive($query)
    {
        return $query->where('ie_situacao', 'A');
    }

    /**
     * Buscar por descrição
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('ds_motivo', 'LIKE', "%{$search}%");
    }

    // ========== MÉTODOS AUXILIARES ==========

    /**
     * Verifica se o motivo está ativo
     */
    public function isActive(): bool
    {
        return $this->ie_situacao === 'A';
    }

    /**
     * Retorna descrição limpa
     */
    public function getCleanDescriptionAttribute(): string
    {
        return trim($this->ds_motivo ?? 'Motivo não especificado');
    }

    // ========== MÉTODOS ESTÁTICOS ==========

    /**
     * Busca todos os motivos ativos
     */
    public static function getAllActive(): array
    {
        return static::active()
            ->orderBy('ds_motivo')
            ->get()
            ->map(function($reason) {
                return [
                    'id' => $reason->nr_sequencia,
                    'descricao' => $reason->clean_description,
                ];
            })
            ->toArray();
    }

    /**
     * Busca motivos mais utilizados
     */
    public static function getMostUsed($limit = 10): array
    {
        return static::withCount('isolations')
            ->active()
            ->orderByDesc('isolations_count')
            ->limit($limit)
            ->get()
            ->map(function($reason) {
                return [
                    'id' => $reason->nr_sequencia,
                    'descricao' => $reason->clean_description,
                    'total_usos' => $reason->isolations_count,
                ];
            })
            ->toArray();
    }
}
