<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Create extension if not exists
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            // Create the GiST index with operator class specified for both columns
            DB::statement('CREATE INDEX entries_slug_path_gist ON entries USING gist (type gist_trgm_ops, slug gist_trgm_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS entries_slug_path_gist');
        }
    }
};
