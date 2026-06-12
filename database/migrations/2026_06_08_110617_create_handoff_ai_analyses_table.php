<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handoff_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->enum('analysis_type', ['general', 'sector'])->default('general');
            $table->unsignedInteger('sector_id')->nullable();
            $table->string('sector_name', 120)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_messages')->default(0);
            $table->unsignedInteger('total_patients')->default(0);
            $table->unsignedInteger('total_professionals')->default(0);
            $table->string('prompt_version', 20)->default('v1');
            $table->text('prompt_used')->nullable();
            $table->json('dataset_metadata')->nullable();
            $table->longText('analysis_text')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['analysis_type', 'sector_id', 'period_start', 'period_end'], 'idx_type_sector_period');
            $table->index('generated_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handoff_ai_analyses');
    }
};
