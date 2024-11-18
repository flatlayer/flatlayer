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
            // Increased slug length to handle paths
            $table->string('slug', 1024);
            $table->text('content')->nullable();
            $table->string('excerpt')->nullable();
            $table->json('meta')->nullable();
            $table->string('filename', 1024);

            // Create indexes for efficient queries
            $table->unique(['type', 'slug']);

            // Add a vector column for search functionality
            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->vector('embedding', 1536)->nullable();
                // Add a GiST index for path-based queries
                DB::statement('CREATE INDEX entries_slug_path_gist ON entries USING gist (type, slug gist_trgm_ops)');
            } else {
                $table->text('embedding')->nullable();
            }

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Add a functional index for the meta JSON field in PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX entries_meta_gin_index ON entries USING GIN ((meta::jsonb))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
