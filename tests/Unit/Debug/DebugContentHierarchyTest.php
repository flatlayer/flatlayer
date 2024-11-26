<?php

namespace Tests\Debug;

use App\Models\Entry;
use App\Services\Content\ContentHierarchy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugContentHierarchyTest extends TestCase
{
    public function test_debug_live_hierarchy()
    {
        // Configure and establish direct database connection
        Config::set('database.default', 'pgsql');
        Config::set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'url' => null,
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => 'pixashot-website',
            'username' => 'postgres',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        // Force reconnection with new config
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        // Enable query logging
        DB::enableQueryLog();

        // Create service
        $service = app(ContentHierarchy::class);

        // Verify connection
        echo "\nConnected to database: " . DB::connection()->getDatabaseName() . "\n";

        // Count entries
        $totalEntries = Entry::count();
        echo "\nTotal entries in database: {$totalEntries}\n";

        // Build hierarchy
        $hierarchy = $service->buildHierarchy('docs', null, [
            'fields' => ['id', 'title', 'slug', 'meta'],
            'depth' => null,
            'sort' => ['meta.nav_order' => 'asc']
        ]);

        // Output results
        $this->outputHierarchy($hierarchy);

        // Show queries
        $queries = DB::getQueryLog();
        echo "\n\nQueries executed:\n";
        foreach ($queries as $query) {
            echo "\n" . $query['query'];
            echo "\nBindings: " . json_encode($query['bindings']) . "\n";
        }
    }

    protected function outputHierarchy(array $nodes, int $level = 0)
    {
        foreach ($nodes as $node) {
            $indent = str_repeat('  ', $level);
            echo "\n{$indent}• {$node['title']} (slug: {$node['slug']})";

            if (isset($node['meta']) && !empty($node['meta'])) {
                echo "\n{$indent}  meta: " . json_encode($node['meta']);
            }

            if (!empty($node['children'])) {
                $this->outputHierarchy($node['children'], $level + 1);
            }
        }
    }
}
