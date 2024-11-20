<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use App\Services\Content\ContentSyncManager;
use App\Services\Storage\StorageResolver;
use CzProject\GitPhp\Git;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EntrySyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $syncService;

    protected $git;

    protected $diskResolver;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->syncService = Mockery::mock(ContentSyncManager::class);
        $this->git = Mockery::mock(Git::class);
        $this->diskResolver = Mockery::mock(StorageResolver::class);

        $this->app->instance(ContentSyncManager::class, $this->syncService);
        $this->app->instance(Git::class, $this->git);
        $this->app->instance(StorageResolver::class, $this->diskResolver);

        Queue::fake();
    }

    public function test_sync_command_with_repository_config()
    {
        Config::set('flatlayer.repositories.post', [
            'disk' => 'content.post',
            'webhook_url' => 'http://example.com/webhook',
            'pull' => true,
        ]);

        Config::set('filesystems.disks.content.post', [
            'driver' => 'local',
            'root' => Storage::path('posts'),
        ]);

        $this->createTestFiles();

        $this->diskResolver->shouldReceive('resolve')
            ->with('content.post', 'post')
            ->andReturn(Storage::disk('local'));

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

    public function test_sync_command_with_direct_disk()
    {
        $this->createTestFiles();

        Config::set('filesystems.disks.content.post', [
            'driver' => 'local',
            'root' => Storage::path('posts'),
        ]);

        $this->diskResolver->shouldReceive('resolve')
            ->with('content.post', 'post')
            ->andReturn(Storage::disk('local'));

        $this->syncService->shouldReceive('sync')
            ->once()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'content.post',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_sync_command_updates_and_deletes()
    {
        $this->createTestFiles();

        $this->diskResolver->shouldReceive('resolve')
            ->twice()
            ->with('local', 'post')
            ->andReturn(Storage::disk('local'));

        $this->syncService->shouldReceive('sync')
            ->twice()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 1,
                'entries_created' => 1,
                'entries_deleted' => 1,
                'skipped' => false,
            ]);

        // Initial sync
        Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'local',
        ]);

        // Simulate file changes
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Updated Post 1\n---\nUpdated Content 1");
        Storage::disk('local')->delete('posts/post2.md');
        Storage::disk('local')->put('posts/post3.md', "---\ntitle: Test Post 3\n---\nContent 3");

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'local',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_sync_command_with_unconfigured_type()
    {
        $this->diskResolver->shouldReceive('resolve')
            ->with(null, 'invalid')
            ->andThrow(new \InvalidArgumentException('No repository configured for type: invalid'));

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'invalid']);

        $this->assertEquals(1, $exitCode);
    }

    public function test_sync_command_with_invalid_disk()
    {
        $this->diskResolver->shouldReceive('resolve')
            ->with('invalid', 'post')
            ->andThrow(new \InvalidArgumentException("Disk 'invalid' is not configured"));

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'invalid',
        ]);

        $this->assertEquals(1, $exitCode);
    }

    public function test_sync_command_with_pull_option()
    {
        $this->createTestFiles();

        $this->diskResolver->shouldReceive('resolve')
            ->with('local', 'post')
            ->andReturn(Storage::disk('local'));

        $this->syncService->shouldReceive('sync')
            ->once()
            ->with(
                'post',
                Mockery::type('Illuminate\Contracts\Filesystem\Filesystem'),
                true,
                false,
            )
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'local',
            '--pull' => true,
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_sync_command_with_dispatch_option()
    {
        Config::set('flatlayer.repositories.post', [
            'disk' => 'content.post',
            'webhook_url' => 'http://example.com/webhook',
        ]);

        Config::set('filesystems.disks.content.post', [
            'driver' => 'local',
            'root' => Storage::path('posts'),
        ]);

        $this->diskResolver->shouldReceive('resolve')
            ->with('content.post', 'post')
            ->andReturn(Storage::disk('local'));

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--dispatch' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        Queue::assertPushed(EntrySyncJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['type'] === 'post'
                && $config['webhookUrl'] === 'http://example.com/webhook';
        });
    }

    protected function createTestFiles()
    {
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Test Post 1\n---\nContent 1");
        Storage::disk('local')->put('posts/post2.md', "---\ntitle: Test Post 2\n---\nContent 2");
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
