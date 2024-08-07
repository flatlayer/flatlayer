<?php

namespace Tests\Fakes\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fakes\TestFilterModel;

class TestFilterModelFactory extends Factory
{
    protected $model = TestFilterModel::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'age' => $this->faker->numberBetween(18, 60),
            'is_active' => $this->faker->boolean,
            'description' => $this->faker->sentence,
            'embedding' => $this->generateEmbedding(),
        ];
    }

    /**
     * Generate a random embedding vector of 1536 dimensions.
     *
     * @return array
     */
    protected function generateEmbedding(): array
    {
        return array_map(fn() => mt_rand() / mt_getrandmax(), array_fill(0, 768, 0));
    }
}
