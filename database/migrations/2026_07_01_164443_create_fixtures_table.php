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
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->string('round');
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('matchday')->nullable();

            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('home_placeholder')->nullable();
            $table->string('away_placeholder')->nullable();

            $table->dateTime('kickoff_at');
            $table->string('venue')->nullable();
            $table->string('status')->default('scheduled');

            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->unsignedTinyInteger('home_score_et')->nullable();
            $table->unsignedTinyInteger('away_score_et')->nullable();
            $table->unsignedTinyInteger('home_pens')->nullable();
            $table->unsignedTinyInteger('away_pens')->nullable();
            $table->unsignedTinyInteger('minute')->nullable();
            $table->string('period')->nullable();

            $table->string('external_id_football_data')->nullable();
            $table->string('external_id_api_football')->nullable();
            $table->dateTime('last_polled_at')->nullable();
            $table->string('last_polled_source')->nullable();

            $table->timestamps();

            $table->unique('external_id_football_data');
            $table->unique('external_id_api_football');
            $table->index(['status', 'kickoff_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
