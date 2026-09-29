<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory()->published(),
            'user_id' => User::factory(),
            'started_at' => now(),
            'submitted_at' => null,
            'score' => null,
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
        ];
    }

    /**
     * Indicate that the attempt has been completed with a given score.
     */
    public function completed(int $score = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now(),
            'score' => $score,
            'status' => QuizAttempt::STATUS_COMPLETED,
        ]);
    }
}
