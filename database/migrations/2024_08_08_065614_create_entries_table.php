<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title')->nullable();
            $table->string('slug');
            $table->text('content')->nullable();
            $table->string('excerpt')->nullable();
            $table->json('meta')->nullable();
            $table->string('filename');

            // Create a composite unique index on type and slug
            $table->unique(['type', 'slug']);

            // Add a vector column for search functionality
            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->vector('embedding', 1536)->nullable();
            } else {
                $table->text('embedding');
            }

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Add a functional index for the meta JSON field
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX entries_meta_gin_index ON entries USING GIN ((meta::jsonb))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
