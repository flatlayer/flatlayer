<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use App\Services\SyncConfigurationService;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
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

    protected $git;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);

        $this->git = Mockery::mock(Git::class);
        $this->app->instance(Git::class, $this->git);

        Queue::fake();
    }

    public function test_entry_sync_command_with_path()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([]);

        $exitCode = Artisan::call('flatlayer:sync', ['--path' => Storage::path('posts'), '--type' => 'post']);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 1', 'type' => 'post']);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 2', 'type' => 'post']);
    }

    public function test_entry_sync_command_with_type()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([
            'PATH' => Storage::path('posts'),
            'PATTERN' => '*.md',
        ]);

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'post']);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 1', 'type' => 'post']);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 2', 'type' => 'post']);
    }

    public function test_entry_sync_command_updates_and_deletes()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([]);

        Artisan::call('flatlayer:sync', ['--path' => Storage::path('posts'), '--type' => 'post']);

        // Simulate file changes
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Updated Post 1\n---\nUpdated Content 1");
        Storage::disk('local')->delete('posts/post2.md');
        Storage::disk('local')->put('posts/post3.md', "---\ntitle: Test Post 3\n---\nContent 3");

        $exitCode = Artisan::call('flatlayer:sync', ['--path' => Storage::path('posts'), '--type' => 'post']);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseHas('entries', ['title' => 'Updated Post 1', 'type' => 'post']);
        $this->assertDatabaseMissing('entries', ['title' => 'Test Post 2', 'type' => 'post']);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 3', 'type' => 'post']);
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

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'custom']);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseHas('entries', ['title' => 'Custom Post 1', 'type' => 'custom']);
        $this->assertDatabaseHas('entries', ['title' => 'Custom Post 2', 'type' => 'custom']);
    }

    public function test_entry_sync_command_with_pull_option()
    {
        $this->createTestFiles();

        $this->syncConfigService->shouldReceive('getConfig')->with('post')->andReturn([
            'PATH' => Storage::path('posts'),
            'PATTERN' => '*.md',
            'PULL' => true,
        ]);

        $gitRepo = Mockery::mock(GitRepository::class);
        $gitRepo->shouldReceive('pull')->once();
        $gitRepo->shouldReceive('getLastCommitId->toString')->twice()->andReturn('old-hash', 'new-hash');

        $this->git->shouldReceive('open')->andReturn($gitRepo);

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

        // Verify that a job was dispatched with the correct webhook URL
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
