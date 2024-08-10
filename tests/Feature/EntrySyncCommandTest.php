<?php

namespace Tests\Feature;

use App\Services\SyncConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EntrySyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $syncConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->syncConfigService = Mockery::mock(SyncConfigurationService::class);
        $this->app->instance(SyncConfigurationService::class, $this->syncConfigService);
    }

    public function test_entry_sync_command_with_path()
    {
        $this->createTestFiles();

        $exitCode = Artisan::call('flatlayer:entry-sync', ['path' => Storage::path('posts')]);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 1', 'type' => 'post']);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 2', 'type' => 'post']);
    }

    public function test_entry_sync_command_with_type()
    {
        $this->createTestFiles();

        // Mock SyncConfigurationService for 'post' type
        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('post')
            ->andReturn(true);

        $this->syncConfigService->shouldReceive('getConfig')
            ->with('post')
            ->andReturn([
                'path' => Storage::path('posts'),
                '--pattern' => '*.md',
            ]);

        $exitCode = Artisan::call('flatlayer:entry-sync', ['--type' => 'post']);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 1', 'type' => 'post']);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 2', 'type' => 'post']);
    }

    public function test_entry_sync_command_updates_and_deletes()
    {
        $this->createTestFiles();
        Artisan::call('flatlayer:entry-sync', ['path' => Storage::path('posts')]);

        // Simulate file changes
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Updated Post 1\n---\nUpdated Content 1");
        Storage::disk('local')->delete('posts/post2.md');
        Storage::disk('local')->put('posts/post3.md', "---\ntitle: Test Post 3\n---\nContent 3");

        $exitCode = Artisan::call('flatlayer:entry-sync', ['path' => Storage::path('posts')]);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseHas('entries', ['title' => 'Updated Post 1', 'type' => 'post']);
        $this->assertDatabaseMissing('entries', ['title' => 'Test Post 2', 'type' => 'post']);
        $this->assertDatabaseHas('entries', ['title' => 'Test Post 3', 'type' => 'post']);
    }

    public function test_entry_sync_command_with_invalid_type()
    {
        $this->syncConfigService->shouldReceive('hasConfig')
            ->with('invalid-type')
            ->andReturn(false);

        $exitCode = Artisan::call('flatlayer:entry-sync', ['--type' => 'invalid-type']);

        $this->assertEquals(1, $exitCode);
    }

    public function test_entry_sync_command_with_invalid_path()
    {
        $exitCode = Artisan::call('flatlayer:entry-sync', ['path' => '/non/existent/path']);

        $this->assertEquals(1, $exitCode);
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
