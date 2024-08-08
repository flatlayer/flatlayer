<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->string('type');  // Add type field
            $table->string('title');
            $table->string('slug');
            $table->text('content');
            $table->json('meta')->nullable();

            // Create a composite unique index on type and slug
            $table->unique(['type', 'slug']);

            // Add a GIN index for the meta JSON field for better performance on JSON queries
            $table->index('meta', null, 'gin');

            // Add a vector column for search functionality
            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->vector('embedding', 768)->nullable();
            }
            else {
                $table->text('embedding');
            }

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
