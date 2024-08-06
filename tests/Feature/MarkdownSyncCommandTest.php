<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Fakes\FakePost;

class MarkdownSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function testMarkdownSyncCommand()
    {
        // Create some markdown files
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Test Post 1\n---\nContent 1");
        Storage::disk('local')->put('posts/post2.md', "---\ntitle: Test Post 2\n---\nContent 2");

        // Run the command
        $exitCode = Artisan::call('flatlayer:markdown-sync', ['model' => 'FakePost']);

        // Assert that the command was successful
        $this->assertEquals(0, $exitCode);

        // Assert that the posts were created in the database
        $this->assertDatabaseHas('posts', ['title' => 'Test Post 1']);
        $this->assertDatabaseHas('posts', ['title' => 'Test Post 2']);

        // Modify an existing file
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Updated Post 1\n---\nUpdated Content 1");

        // Delete a file
        Storage::disk('local')->delete('posts/post2.md');

        // Add a new file
        Storage::disk('local')->put('posts/post3.md', "---\ntitle: Test Post 3\n---\nContent 3");

        // Run the command again
        $exitCode = Artisan::call('flatlayer:markdown-sync', ['model' => 'FakePost']);

        // Assert that the command was successful
        $this->assertEquals(0, $exitCode);

        // Assert the changes were reflected in the database
        $this->assertDatabaseHas('posts', ['title' => 'Updated Post 1']);
        $this->assertDatabaseMissing('posts', ['title' => 'Test Post 2']);
        $this->assertDatabaseHas('posts', ['title' => 'Test Post 3']);

        // Assert the total number of posts is correct
        $this->assertEquals(2, FakePost::count());
    }

    public function testMarkdownSyncCommandWithInvalidModel()
    {
        $exitCode = Artisan::call('flatlayer:markdown-sync', ['model' => 'InvalidModel']);

        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('Model class for \'InvalidModel\' does not exist.', Artisan::output());
    }

    public function testMarkdownSyncCommandWithNonEloquentModel()
    {
        $exitCode = Artisan::call('flatlayer:markdown-sync', ['model' => 'stdClass']);

        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('The provided class must be a sluggable Eloquent model.', Artisan::output());
    }
}
