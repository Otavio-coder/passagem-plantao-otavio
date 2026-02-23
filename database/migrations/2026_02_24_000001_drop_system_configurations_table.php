<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove a tabela system_configurations pois agora usamos user_sector_preferences
        Schema::dropIfExists('system_configurations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recria a tabela caso necessário rollback
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('hospital_code', 50)->nullable()->index();
            $table->string('hospital_name', 255)->nullable();
            $table->string('sector_code', 50)->nullable()->index();
            $table->string('sector_name', 255)->nullable();
            $table->string('bed_code', 50)->nullable()->index();
            $table->string('bed_name', 255)->nullable();
            $table->enum('configuration_type', ['hospital', 'sector', 'bed'])->default('hospital');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('configured_by')->nullable();
            $table->timestamps();
            
            $table->index(['configuration_type', 'is_active']);
        });
    }
};
