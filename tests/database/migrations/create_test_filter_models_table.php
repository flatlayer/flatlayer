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
        Schema::create('test_filter_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('age');
            $table->boolean('is_active');
            $table->text('description')->nullable();
            $table->json('embedding')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_filter_models');
    }
};
