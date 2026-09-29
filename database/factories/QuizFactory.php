<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
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
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => Quiz::STATUS_DRAFT,
            'time_limit_minutes' => null,
            'passing_score' => 70,
        ];
    }

    /**
     * Indicate that the quiz is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Quiz::STATUS_PUBLISHED,
        ]);
    }

    /**
     * Indicate that the quiz is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Quiz::STATUS_DRAFT,
        ]);
    }
}
