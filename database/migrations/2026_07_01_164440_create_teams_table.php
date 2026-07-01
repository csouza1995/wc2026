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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('fifa_code', 3);
            $table->string('flag_url')->nullable();
            $table->string('confederation')->nullable();
            $table->string('external_id_football_data')->nullable();
            $table->timestamps();

            $table->unique('fifa_code');
            $table->unique('external_id_football_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
