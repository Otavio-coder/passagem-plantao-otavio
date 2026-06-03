<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixChatSectorNames extends Command
{
    protected $signature = 'chat:fix-sector-names {--dry-run : Mostra o que seria alterado sem executar}';

    protected $description = 'Corrige sector_name/sector_id incorretos em chat_messages';

    private const MAPPINGS = [
        // Mapeamento inicial
        'HSC  Int CC' => ['HNT-U.I.4ºandar', 6236],
        'HNT-INT-CC-HSC' => ['HNT-U.I.4ºandar', 6236],
        'HSF-Internação Centro Cirúrgico (CC485)' => ['HNT-U.I.4ºandar', 6236],
        'HSR-Intern.CCir' => ['HSR 2º PRT/CNV',  1147],
        // Inferido por setor predominante dos autores
        'HNT-UTI' => ['HNT-U.I.4ºandar', 6236],
        'HNT-Rec Int Obs' => ['HNT-U.I.4ºandar', 6236],
        'HSJ-Inter CC' => ['HNT-U.I.4ºandar', 6236],
        'HSR - UTI' => ['HSR 2º PRT/CNV',  1147],
        'HSR 5º PRT/CNV' => ['HSR 2º PRT/CNV',  1147],
        'HSC-UTI 5°-HDVS' => ['HSR 2º PRT/CNV',  1147],
        'HDVS-CIEM Internação (CC191)' => ['HSR 2º PRT/CNV',  1147],
        'HDVS-Int.CCA' => ['HSR 2º PRT/CNV',  1147],
        'HSF 2º PRT/CNV' => ['HSR 2º PRT/CNV',  1147],
    ];

    // Usuários cujas mensagens sem setor são lixo de teste — mantém null
    private const NULLIFY_USERS = [
        'Caio Foti Pontes',
        'Rafaela Campiol',
        'Pedro Henrique Rosa Lacerda da Silva',
        'Nicheller Araujo Bitencourt Mambrum',
    ];

    // Usuários sem setor que devem ir para HNT 4 Andar
    private const FIX_HNT_USERS = [
        'Bruna Silveira da Silva',
    ];

    private const NULLIFY = [
        '',
        '[HSR 2º PRT/CNV"]"',
    ];

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        if ($dry) {
            $this->warn('DRY RUN — nenhuma alteração será gravada.');
        }

        $this->line('');
        $this->line('<fg=cyan>Situação atual:</>');

        $current = DB::table('chat_messages')
            ->selectRaw('sector_name, sector_id, COUNT(*) as cnt')
            ->groupBy('sector_name', 'sector_id')
            ->orderByDesc('cnt')
            ->get();

        $this->table(['sector_name', 'sector_id', 'msgs'], $current->map(fn ($r) => [
            $r->sector_name ?: '(vazio)',
            $r->sector_id ?: '—',
            $r->cnt,
        ])->toArray());

        $this->line('');
        $totalChanged = 0;

        foreach (self::MAPPINGS as $from => [$toName, $toId]) {
            $count = DB::table('chat_messages')->where('sector_name', $from)->count();

            if ($count === 0) {
                continue;
            }

            $this->line("  <fg=yellow>{$from}</> ({$count} msgs) → <fg=green>{$toName}</> [sector_id={$toId}]");

            if (! $dry) {
                DB::table('chat_messages')
                    ->where('sector_name', $from)
                    ->update(['sector_name' => $toName, 'sector_id' => $toId]);
            }

            $totalChanged += $count;
        }

        foreach (self::NULLIFY as $invalid) {
            $count = DB::table('chat_messages')->where('sector_name', $invalid)->count();

            if ($count === 0) {
                continue;
            }

            $label = $invalid === '' ? '(vazio)' : $invalid;
            $this->line("  <fg=yellow>{$label}</> ({$count} msgs) → NULL");

            if (! $dry) {
                DB::table('chat_messages')
                    ->where('sector_name', $invalid)
                    ->update(['sector_name' => null, 'sector_id' => null]);
            }

            $totalChanged += $count;
        }

        // ── Preencher nulos pelo setor predominante do usuário ───────────────
        $nullRows = DB::table('chat_messages')
            ->whereNull('sector_name')
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->get();

        foreach ($nullRows as $row) {
            $dominant = DB::table('chat_messages')
                ->where('user_id', $row->user_id)
                ->whereNotNull('sector_name')
                ->selectRaw('sector_name, sector_id, COUNT(*) as cnt')
                ->groupBy('sector_name', 'sector_id')
                ->orderByDesc('cnt')
                ->first();

            if (! $dominant) {
                continue;
            }

            $this->line("  user_id={$row->user_id} ({$row->cnt} msgs sem setor) → <fg=green>{$dominant->sector_name}</> por predominância");

            if (! $dry) {
                DB::table('chat_messages')
                    ->where('user_id', $row->user_id)
                    ->whereNull('sector_name')
                    ->update(['sector_name' => $dominant->sector_name, 'sector_id' => $dominant->sector_id]);
            }

            $totalChanged += $row->cnt;
        }

        // ── Corrigir por usuário (regras manuais) ─────────────────────────────
        // ── Deletar TODAS as msgs de usuários de teste ───────────────────────
        $testUserIds = DB::table('users')->whereIn('name', self::NULLIFY_USERS)->pluck('id');
        $nTestUsers = DB::table('chat_messages')->whereIn('user_id', $testUserIds)->count();
        if ($nTestUsers > 0) {
            $this->line("  Todas as msgs de usuários de teste ({$nTestUsers}) → DELETE");
            if (! $dry) {
                DB::table('chat_messages')->whereIn('user_id', $testUserIds)->delete();
            }
            $totalChanged += $nTestUsers;
        }

        // ── Deletar msgs com conteúdo de teste de qualquer usuário ───────────
        $nTestContent = DB::table('chat_messages')
            ->whereRaw("LOWER(content) REGEXP '^(teste|test|testing|aaa+|abc|xxx+|zzz+|[0-9]+)$'")
            ->count();
        if ($nTestContent > 0) {
            $this->line("  Msgs com conteúdo de teste ({$nTestContent}) → DELETE");
            if (! $dry) {
                DB::table('chat_messages')
                    ->whereRaw("LOWER(content) REGEXP '^(teste|test|testing|aaa+|abc|xxx+|zzz+|[0-9]+)$'")
                    ->delete();
            }
            $totalChanged += $nTestContent;
        }

        // ── Deletar nulos restantes ───────────────────────────────────────────
        $nLoose = DB::table('chat_messages')->whereNull('sector_name')->count();
        if ($nLoose > 0) {
            $this->line("  Msgs sem setor restantes ({$nLoose}) → DELETE");
            if (! $dry) {
                DB::table('chat_messages')->whereNull('sector_name')->delete();
            }
            $totalChanged += $nLoose;
        }

        $hntUserIds = DB::table('users')->whereIn('name', self::FIX_HNT_USERS)->pluck('id');
        $nHnt = DB::table('chat_messages')->whereIn('user_id', $hntUserIds)->whereNull('sector_name')->count();
        if ($nHnt > 0) {
            $this->line("  Msgs sem setor de {$nHnt} usuários → <fg=green>HNT-U.I.4ºandar</> [sector_id=6236]");
            if (! $dry) {
                DB::table('chat_messages')->whereIn('user_id', $hntUserIds)->whereNull('sector_name')
                    ->update(['sector_name' => 'HNT-U.I.4ºandar', 'sector_id' => 6236]);
            }
            $totalChanged += $nHnt;
        }

        $this->line('');

        if ($dry) {
            $this->warn("DRY RUN: {$totalChanged} mensagens seriam alteradas.");
        } else {
            $this->info("{$totalChanged} mensagens atualizadas.");
        }

        return self::SUCCESS;
    }
}
