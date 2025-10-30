<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    if (Schema::hasTable('system_configurations')) {
        // Se a tabela já existe, apenas limpa os dados
        DB::table('system_configurations')->truncate();
    } else {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('hospital_code')->nullable()->index();
            $table->string('hospital_name')->nullable();
            $table->string('sector_code')->nullable();
            $table->string('sector_name')->nullable();
            $table->string('bed_code')->nullable();
            $table->string('bed_name')->nullable();
            $table->boolean('is_active')->default(1)->index();
            $table->enum('configuration_type', ['hospital', 'sector', 'bed'])->index();
            $table->unsignedBigInteger('configured_by')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configurations');
    }
};