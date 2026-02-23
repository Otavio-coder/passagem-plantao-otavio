<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('chat_auditoria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mensagem_id')->nullable();
            $table->unsignedBigInteger('sessao_id')->nullable();
            $table->string('nr_atendimento', 50)->index();
            $table->string('turno_id', 20);
            $table->string('acao', 100);
            $table->unsignedBigInteger('usuario_id');
            $table->timestamp('dt_acao')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('detalhes_acao')->nullable();
            $table->json('estado_anterior')->nullable();
            $table->json('estado_posterior')->nullable();
            $table->timestamps();

            $table->index(['nr_atendimento', 'acao']);
            $table->index(['usuario_id', 'dt_acao']);
        });

        Schema::create('chat_mensagens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sessao_id');
            $table->string('nr_atendimento', 50)->index();
            $table->string('turno_id', 20)->index();
            $table->unsignedBigInteger('usuario_id')->index();
            $table->text('mensagem');
            $table->timestamp('dt_criacao')->useCurrent();
            $table->timestamp('dt_edicao')->nullable();
            $table->boolean('is_fixed')->default(false);
            $table->unsignedBigInteger('fixed_by')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('expiracao')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index(['nr_atendimento', 'turno_id', 'dt_criacao']);
            $table->index(['sessao_id', 'is_deleted']);
        });

        Schema::create('chat_sessoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nr_atendimento', 50)->index();
            $table->string('turno_id', 20)->index();
            $table->date('data_sessao')->index();
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fim')->nullable();
            $table->boolean('encerrada')->default(false);
            $table->json('usuarios_participantes')->nullable();
            $table->integer('total_mensagens')->default(0);
            $table->timestamps();

            $table->index(['nr_atendimento', 'data_sessao']);
            $table->unique(['nr_atendimento', 'turno_id', 'data_sessao']);
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 1);
            $table->string('message', 100);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id')->index('role_has_permissions_role_id_foreign');

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('system_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('hospital_code')->nullable()->index();
            $table->string('hospital_name')->nullable();
            $table->string('sector_code')->nullable();
            $table->string('sector_name')->nullable();
            $table->string('bed_code')->nullable();
            $table->string('bed_name')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->enum('configuration_type', ['hospital', 'sector', 'bed'])->index();
            $table->unsignedBigInteger('configured_by')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 60);
            $table->string('name', 100);
            $table->string('email', 100);
            $table->longText('photo')->nullable();
            $table->string('password')->nullable();
            $table->string('status', 1)->default('A');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
            $table->string('guid')->nullable()->unique();
            $table->string('domain')->nullable();
        });

        Schema::table('chat_mensagens', function (Blueprint $table) {
            $table->foreign(['sessao_id'])->references(['id'])->on('chat_sessoes')->onUpdate('no action')->onDelete('cascade');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->foreign(['permission_id'])->references(['id'])->on('permissions')->onUpdate('no action')->onDelete('cascade');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->foreign(['role_id'])->references(['id'])->on('roles')->onUpdate('no action')->onDelete('cascade');
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->foreign(['permission_id'])->references(['id'])->on('permissions')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['role_id'])->references(['id'])->on('roles')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->dropForeign('role_has_permissions_permission_id_foreign');
            $table->dropForeign('role_has_permissions_role_id_foreign');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign('model_has_roles_role_id_foreign');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign('model_has_permissions_permission_id_foreign');
        });

        Schema::table('chat_mensagens', function (Blueprint $table) {
            $table->dropForeign('chat_mensagens_sessao_id_foreign');
        });

        Schema::dropIfExists('users');

        Schema::dropIfExists('system_configurations');

        Schema::dropIfExists('roles');

        Schema::dropIfExists('role_has_permissions');

        Schema::dropIfExists('permissions');

        Schema::dropIfExists('model_has_roles');

        Schema::dropIfExists('model_has_permissions');

        Schema::dropIfExists('logs');

        Schema::dropIfExists('jobs');

        Schema::dropIfExists('job_batches');

        Schema::dropIfExists('chat_sessoes');

        Schema::dropIfExists('chat_mensagens');

        Schema::dropIfExists('chat_auditoria');

        Schema::dropIfExists('cache_locks');
    }
};
