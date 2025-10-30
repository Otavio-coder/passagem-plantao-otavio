<?php

namespace App\Models\EMR\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model para CIH_PRECAUCAO (Tipos de Precaução)
 *
 * Representa os tipos de precaução/isolamento que podem ser aplicados.
 * Exemplos: Precaução de Contato, Precaução Respiratória, Precaução para Gotículas, etc.
 */
class Precaution extends Model
{
    protected $connection = 'tasy';
    protected $table = 'TASY.CIH_PRECAUCAO';
    protected $primaryKey = 'nr_sequencia';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    // ========== RELAÇÕES ==========

    /**
     * Isolamentos que usam este tipo de precaução
     */
    public function isolations(): HasMany
    {
        return $this->hasMany(Isolation::class, 'nr_seq_precaucao', 'nr_sequencia');
    }

    // ========== SCOPES ==========

    /**
     * Precauções ativas
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
        return $query->where('ds_precaucao', 'LIKE', "%{$search}%");
    }

    /**
     * Ordenar por descrição
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('ds_precaucao');
    }

    // ========== MÉTODOS AUXILIARES ==========

    /**
     * Verifica se a precaução está ativa
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
        return trim($this->ds_precaucao ?? 'Precaução não especificada');
    }

    /**
     * Retorna cor baseada no tipo de precaução
     */
    public function getColorAttribute(): string
    {
        $description = strtoupper($this->ds_precaucao ?? '');

        // Mapeamento de cores baseado em palavras-chave
        if (str_contains($description, 'CONTATO')) {
            return 'yellow'; // Amarelo para precaução de contato
        }

        if (str_contains($description, 'RESPIRAT') || str_contains($description, 'AEROSS')) {
            return 'red'; // Vermelho para precaução respiratória/aerossóis
        }

        if (str_contains($description, 'GOTÍCULA') || str_contains($description, 'GOTICULA')) {
            return 'orange'; // Laranja para precaução de gotículas
        }

        if (str_contains($description, 'PROTETOR')) {
            return 'blue'; // Azul para precaução protetora
        }

        return 'gray'; // Cinza para outros tipos
    }

    /**
     * Retorna ícone SVG baseado no tipo
     */
    public function getIconAttribute(): string
    {
        $description = strtoupper($this->ds_precaucao ?? '');

        if (str_contains($description, 'CONTATO')) {
            return 'hand'; // Ícone de mão
        }

        if (str_contains($description, 'RESPIRAT') || str_contains($description, 'AEROSS')) {
            return 'mask'; // Ícone de máscara
        }

        if (str_contains($description, 'GOTÍCULA') || str_contains($description, 'GOTICULA')) {
            return 'droplet'; // Ícone de gotícula
        }

        if (str_contains($description, 'PROTETOR')) {
            return 'shield'; // Ícone de escudo
        }

        return 'alert'; // Ícone de alerta genérico
    }

    // ========== MÉTODOS ESTÁTICOS ==========

    /**
     * Busca todas as precauções ativas
     */
    public static function getAllActive(): array
    {
        return static::active()
            ->ordered()
            ->get()
            ->map(function($precaution) {
                return [
                    'id' => $precaution->nr_sequencia,
                    'descricao' => $precaution->clean_description,
                    'cor' => $precaution->color,
                    'icone' => $precaution->icon,
                ];
            })
            ->toArray();
    }

    /**
     * Busca precauções mais utilizadas
     */
    public static function getMostUsed($limit = 10): array
    {
        return static::withCount('isolations')
            ->active()
            ->orderByDesc('isolations_count')
            ->limit($limit)
            ->get()
            ->map(function($precaution) {
                return [
                    'id' => $precaution->nr_sequencia,
                    'descricao' => $precaution->clean_description,
                    'cor' => $precaution->color,
                    'total_usos' => $precaution->isolations_count,
                ];
            })
            ->toArray();
    }

    /**
     * Busca precauções por cor
     */
    public static function getByColor($color): array
    {
        return static::active()
            ->get()
            ->filter(function($precaution) use ($color) {
                return $precaution->color === $color;
            })
            ->map(function($precaution) {
                return [
                    'id' => $precaution->nr_sequencia,
                    'descricao' => $precaution->clean_description,
                ];
            })
            ->values()
            ->toArray();
    }
}
