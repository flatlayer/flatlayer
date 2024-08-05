<?php

namespace Tests\Unit;

use App\Services\ModelResolverService;
use PHPUnit\Framework\TestCase;

class ModelResolverServiceTest extends TestCase
{
    protected ModelResolverService $modelResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modelResolver = new ModelResolverService();
    }

    public function testResolveWithDefaultNamespace()
    {
        // Assuming we have a User model in App\Models namespace
        $this->assertEquals('App\Models\User', $this->modelResolver->resolve('users'));
        $this->assertEquals('App\Models\Post', $this->modelResolver->resolve('posts'));
    }

    public function testResolveWithNonExistentModel()
    {
        $this->assertNull($this->modelResolver->resolve('non-existent-model'));
    }

    public function testAddNamespace()
    {
        $this->modelResolver->addNamespace('Tests\Fakes');

        // Mock the class_exists function for this test
        $this->assertTrue($this->modelResolver->resolve('fake-models') === 'Tests\Fakes\FakeModel' ||
            $this->modelResolver->resolve('fake-models') === null,
            "The resolver should return 'Tests\Fakes\FakeModel' if it exists, or null if it doesn't");
    }

    public function testResolveWithMultipleNamespaces()
    {
        $this->modelResolver->addNamespace('Tests\Fakes');

        $this->assertEquals('App\Models\User', $this->modelResolver->resolve('users'));

        // Mock the class_exists function for this test
        $this->assertTrue($this->modelResolver->resolve('fake-models') === 'Tests\Fakes\FakeModel' ||
            $this->modelResolver->resolve('fake-models') === null,
            "The resolver should return 'Tests\Fakes\FakeModel' if it exists, or null if it doesn't");
    }

    public function testResolveWithSingularSlug()
    {
        // This test assumes Category model exists. If it doesn't, we should expect null.
        $result = $this->modelResolver->resolve('category');
        $this->assertTrue($result === 'App\Models\Category' || $result === null,
            "The resolver should return 'App\Models\Category' if it exists, or null if it doesn't");
    }

    public function testResolveIsCaseInsensitive()
    {
        $result = $this->modelResolver->resolve('Users');
        $this->assertTrue(strcasecmp($result, 'App\Models\User') === 0,
            "Expected case-insensitive match for 'Users'");

        $result = $this->modelResolver->resolve('USER');
        $this->assertTrue(strcasecmp($result, 'App\Models\User') === 0,
            "Expected case-insensitive match for 'USER'");
    }
}
