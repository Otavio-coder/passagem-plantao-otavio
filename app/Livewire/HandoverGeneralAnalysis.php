<?php

namespace App\Livewire;

use App\Models\HandoverAiAnalysis;
use App\Services\Handover\AiAnalysis\HandoverAiAnalysisService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class HandoverGeneralAnalysis extends Component
{
    public bool $isOpen = false;

    public string $periodStart = '';

    public string $periodEnd = '';

    public ?int $currentAnalysisId = null;

    public bool $confirmRegenerate = false;

    #[On('openHandoverGeneralAnalysis')]
    public function open(): void
    {
        $this->periodEnd = now()->toDateString();
        $this->periodStart = now()->subDays(30)->toDateString();
        $this->confirmRegenerate = false;
        $this->isOpen = true;

        $existing = $this->findExisting();
        if ($existing) {
            $this->currentAnalysisId = $existing->id;
        }
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->currentAnalysisId = null;
        $this->confirmRegenerate = false;
    }

    // ── Reactive ───────────────────────────────────────────────────────────────

    public function updatedPeriodStart(): void
    {
        $this->currentAnalysisId = null;
        $this->confirmRegenerate = false;
        $existing = $this->findExisting();
        if ($existing) {
            $this->currentAnalysisId = $existing->id;
        }
    }

    public function updatedPeriodEnd(): void
    {
        $this->updatedPeriodStart();
    }

    // ── Actions ────────────────────────────────────────────────────────────────

    public function generateAnalysis(): void
    {
        $this->validatePeriod();

        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->confirmRegenerate = false;

        $analysis = HandoverAiAnalysis::create([
            'analysis_type' => 'general',
            'sector_id' => null,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'generated_by' => Auth::id(),
            'status' => 'pending',
        ]);

        $this->currentAnalysisId = $analysis->id;

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
        if ($this->currentAnalysisId === null) {
            return null;
        }

        return HandoverAiAnalysis::find($this->currentAnalysisId);
    }

    #[Computed]
    public function recentAnalyses(): Collection
    {
        return HandoverAiAnalysis::where('analysis_type', 'general')
            ->whereNull('sector_id')
            ->where('status', 'completed')
            ->latest('generated_at')
            ->limit(10)
            ->get();
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('handover.general-analysis-modal', [
            'analysis' => $this->currentAnalysis,
            'recentAnalyses' => $this->recentAnalyses,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function validatePeriod(): void
    {
        $this->validate([
            'periodStart' => ['required', 'date', 'before_or_equal:periodEnd'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart', 'before_or_equal:today'],
        ], [
            'periodStart.required' => 'Informe a data inicial.',
            'periodStart.before_or_equal' => 'Data inicial deve ser anterior à data final.',
            'periodEnd.required' => 'Informe a data final.',
            'periodEnd.before_or_equal' => 'Data final não pode ser futura.',
        ]);
    }

    private function findExisting(): ?HandoverAiAnalysis
    {
        if (! $this->periodStart || ! $this->periodEnd) {
            return null;
        }

        return HandoverAiAnalysis::findCompleted(
            'general',
            Carbon::parse($this->periodStart),
            Carbon::parse($this->periodEnd)
        );
    }
}
