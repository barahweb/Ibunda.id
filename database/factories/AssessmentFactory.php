<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->admin(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => Assessment::STATUS_DRAFT,
        ];
    }

    /**
     * Indicate that the assessment is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Assessment::STATUS_PUBLISHED,
        ]);
    }

    /**
     * Indicate that the assessment is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Assessment::STATUS_DRAFT,
        ]);
    }
}
