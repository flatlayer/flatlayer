<?php

namespace Tests\Unit\Query;

use App\Models\Entry;
use App\Query\EntrySerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    protected EntrySerializer $serializer;
    protected Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new EntrySerializer();
        $this->entry = $this->createEntry();
    }

    protected function createEntry(): Entry
    {
        return Entry::factory()->create([
            'published_at' => '2023-05-15 10:00:00',
        ]);
    }

    public function test_date_casting()
    {
        $fields = [
            ['published_at', 'date'],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsString($result['published_at']);
        $this->assertEquals('2023-05-15', $result['published_at']);
    }

    public function test_datetime_casting()
    {
        $fields = [
            ['published_at', 'datetime'],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsString($result['published_at']);
        $this->assertEquals('2023-05-15 10:00:00', $result['published_at']);
    }
}
