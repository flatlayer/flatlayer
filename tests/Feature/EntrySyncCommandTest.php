<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use App\Services\EntrySyncService;
use App\Services\SyncConfigurationService;
use CzProject\GitPhp\Git;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EntrySyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $syncConfigService;

    protected $syncService;

    protected $git;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->syncService = Mockery::mock(EntrySyncService::class);
        $this->git = Mockery::mock(Git::class);

        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);
        $this->app->instance(EntrySyncService::class, $this->syncService);
        $this->app->instance(Git::class, $this->git);

        Queue::fake();
    }

    public function test_entry_sync_command_with_path()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([]);
        $this->syncService->shouldReceive('sync')
            ->once()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', ['--path' => Storage::path('posts'), '--type' => 'post']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_entry_sync_command_with_type()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([
            'PATH' => Storage::path('posts'),
            'PATTERN' => '*.md',
        ]);

        $this->syncService->shouldReceive('sync')
            ->once()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'post']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_entry_sync_command_updates_and_deletes()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([]);

        $this->syncService->shouldReceive('sync')
            ->twice()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 1,
                'entries_created' => 1,
                'entries_deleted' => 1,
                'skipped' => false,
            ]);

        Artisan::call('flatlayer:sync', ['--path' => Storage::path('posts'), '--type' => 'post']);

        // Simulate file changes
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Updated Post 1\n---\nUpdated Content 1");
        Storage::disk('local')->delete('posts/post2.md');
        Storage::disk('local')->put('posts/post3.md', "---\ntitle: Test Post 3\n---\nContent 3");

        $exitCode = Artisan::call('flatlayer:sync', ['--path' => Storage::path('posts'), '--type' => 'post']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_entry_sync_command_with_invalid_type()
    {
        $this->syncConfigService->shouldReceive('getConfig')->with('invalid-type')->andReturn([]);

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'invalid-type']);

        $this->assertEquals(1, $exitCode);
    }

    public function test_entry_sync_command_with_invalid_path()
    {
        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([]);

        $exitCode = Artisan::call('flatlayer:sync', ['--path' => '/non/existent/path', '--type' => 'post']);

        $this->assertEquals(1, $exitCode);
    }

    public function test_entry_sync_command_with_custom_pattern()
    {
        $this->createTestFiles('custom');

        $this->syncConfigService->shouldReceive('getConfig')->with('custom')->andReturn([
            'PATH' => Storage::path('custom'),
            'PATTERN' => '*.custom',
        ]);

        $this->syncService->shouldReceive('sync')
            ->once()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'custom']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_entry_sync_command_with_pull_option()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([
            'PATH' => Storage::path('posts'),
            'PATTERN' => '*.md',
            'PULL' => true,
        ]);

        $this->syncService->shouldReceive('sync')
            ->once()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'post']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_entry_sync_command_respects_webhook_config()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([
            'PATH' => Storage::path('posts'),
            'PATTERN' => '*.md',
            'WEBHOOK' => 'http://example.com/webhook',
        ]);

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'post', '--dispatch' => true]);

        $this->assertEquals(0, $exitCode);

        Queue::assertPushed(function (EntrySyncJob $job) {
            return $job->getJobConfig()['webhookUrl'] === 'http://example.com/webhook';
        });
    }

    protected function createTestFiles($type = 'posts')
    {
        if ($type === 'posts') {
            Storage::disk('local')->put('posts/post1.md', "---\ntitle: Test Post 1\n---\nContent 1");
            Storage::disk('local')->put('posts/post2.md', "---\ntitle: Test Post 2\n---\nContent 2");
        } elseif ($type === 'custom') {
            Storage::disk('local')->put('custom/post1.custom', "---\ntitle: Custom Post 1\n---\nContent 1");
            Storage::disk('local')->put('custom/post2.custom', "---\ntitle: Custom Post 2\n---\nContent 2");
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
