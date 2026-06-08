<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('writing_team_analyses');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['writing_analysis', 'writing_analyzed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('writing_analysis')->nullable()->after('photo');
            $table->timestamp('writing_analyzed_at')->nullable()->after('writing_analysis');
        });

        Schema::create('writing_team_analyses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
