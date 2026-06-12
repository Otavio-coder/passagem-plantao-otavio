<?php

namespace App\Livewire;

use App\Models\HandoverAiAnalysis;
use App\Services\Handover\AiAnalysis\HandoverAiAnalysisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class HandoverSectorAnalysis extends Component
{
    public bool $isOpen = false;

    public ?int $sectorId = null;

    public string $sectorName = '';

    public string $periodStart = '';

    public string $periodEnd = '';

    #[Locked]
    public ?int $analysisId = null;

    public bool $confirmRegenerate = false;

    #[On('openHandoverSectorAnalysis')]
    public function open(int $sectorId, string $sectorName): void
    {
        $this->sectorId = $sectorId;
        $this->sectorName = $sectorName;
        $this->periodEnd = now()->toDateString();
        $this->periodStart = now()->subDays(30)->toDateString();
        $this->confirmRegenerate = false;
        $this->isOpen = true;

        $existing = HandoverAiAnalysis::findCompleted(
            'sector',
            Carbon::parse($this->periodStart),
            Carbon::parse($this->periodEnd),
            $sectorId
        );
        $this->analysisId = $existing?->id;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->analysisId = null;
        $this->confirmRegenerate = false;
    }

    public function updatedPeriodStart(): void
    {
        $this->confirmRegenerate = false;
        $this->refreshExisting();
    }

    public function updatedPeriodEnd(): void
    {
        $this->confirmRegenerate = false;
        $this->refreshExisting();
    }

    // ── Actions ────────────────────────────────────────────────────────────────

    public function generate(): void
    {
        $this->validate([
            'periodStart' => ['required', 'date', 'before_or_equal:periodEnd'],
            'periodEnd' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'periodStart.before_or_equal' => 'Data inicial deve ser anterior à data final.',
            'periodEnd.before_or_equal' => 'Data final não pode ser futura.',
        ]);

        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->confirmRegenerate = false;

        $analysis = HandoverAiAnalysis::create([
            'analysis_type' => 'sector',
            'sector_id' => $this->sectorId,
            'sector_name' => $this->sectorName,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'generated_by' => Auth::id(),
            'status' => 'pending',
        ]);

        $this->analysisId = $analysis->id;

        try {
            app(HandoverAiAnalysisService::class)->analyze($analysis);
        } catch (\Throwable $e) {
            // Status updated to 'failed' inside service
        }
    }

    public function requestRegenerate(): void
    {
        $this->confirmRegenerate = true;
    }

    public function cancelRegenerate(): void
    {
        $this->confirmRegenerate = false;
    }

    // ── Computed ───────────────────────────────────────────────────────────────

    #[Computed]
    public function currentAnalysis(): ?HandoverAiAnalysis
    {
        if ($this->analysisId === null) {
            return null;
        }

        return HandoverAiAnalysis::find($this->analysisId);
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('handover.sector-analysis-modal', [
            'analysis' => $this->currentAnalysis,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function refreshExisting(): void
    {
        if (! $this->periodStart || ! $this->periodEnd || ! $this->sectorId) {
            return;
        }

        $existing = HandoverAiAnalysis::findCompleted(
            'sector',
            Carbon::parse($this->periodStart),
            Carbon::parse($this->periodEnd),
            $this->sectorId
        );
        $this->analysisId = $existing?->id;
    }
}
