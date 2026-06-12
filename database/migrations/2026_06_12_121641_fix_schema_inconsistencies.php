<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige inconsistências de schema identificadas antes do deploy em produção:
 *
 * 1. chat_reactions: remove índice redundante message_id — já coberto pelo unique(message_id, user_id)
 * 2. chat_patient_todos: nr_atendimento int(11) → bigint unsigned, alinhando com handover_activity_log
 * 3. chat_analytics_daily: unique constraint (date, shift, sector_id, user_id) — evita duplicatas no upsert
 * 4. chat_patient_todos.shift: varchar(255) → char(1) — alinha com handover_activity_log e nurse_archive_sessions
 * 5. chat_analytics_daily.sector_name: varchar(255) → varchar(120) — alinha com chat_messages e handover_ai_analyses
 * 6. chat_messages: índice em created_at — coluna mais usada nos filtros de período de analytics
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove índice simples redundante em chat_reactions.message_id
        if ($this->indexExists('chat_reactions', 'chat_reactions_message_id_index')) {
            Schema::table('chat_reactions', function (Blueprint $table) {
                $table->dropIndex('chat_reactions_message_id_index');
            });
        }

        // 2. chat_patient_todos.nr_atendimento: int → bigint unsigned
        Schema::table('chat_patient_todos', function (Blueprint $table) {
            $table->unsignedBigInteger('nr_atendimento')->change();
        });

        // 3. unique constraint em chat_analytics_daily para upsert seguro
        if (! $this->indexExists('chat_analytics_daily', 'analytics_daily_unique_row')) {
            Schema::table('chat_analytics_daily', function (Blueprint $table) {
                $table->unique(['date', 'shift', 'sector_id', 'user_id'], 'analytics_daily_unique_row');
            });
        }

        // 4. chat_patient_todos.shift: varchar(255) → char(1)
        Schema::table('chat_patient_todos', function (Blueprint $table) {
            $table->char('shift', 1)->nullable()->change();
        });

        // 5. chat_analytics_daily.sector_name: varchar(255) → varchar(120)
        Schema::table('chat_analytics_daily', function (Blueprint $table) {
            $table->string('sector_name', 120)->nullable()->change();
        });

        // 6. Índice em chat_messages.created_at para filtros de período
        if (! $this->indexExists('chat_messages', 'chat_messages_created_at_index')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('chat_reactions', function (Blueprint $table) {
            $table->index('message_id');
        });

        Schema::table('chat_patient_todos', function (Blueprint $table) {
            $table->integer('nr_atendimento')->change();
        });

        if ($this->indexExists('chat_analytics_daily', 'analytics_daily_unique_row')) {
            Schema::table('chat_analytics_daily', function (Blueprint $table) {
                $table->dropUnique('analytics_daily_unique_row');
            });
        }

        Schema::table('chat_patient_todos', function (Blueprint $table) {
            $table->string('shift', 255)->nullable()->change();
        });

        Schema::table('chat_analytics_daily', function (Blueprint $table) {
            $table->string('sector_name', 255)->nullable()->change();
        });

        if ($this->indexExists('chat_messages', 'chat_messages_created_at_index')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropIndex('chat_messages_created_at_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return ! empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
    }
};
