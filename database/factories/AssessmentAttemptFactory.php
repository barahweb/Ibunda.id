<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAttempt>
 */
class AssessmentAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory()->published(),
            'user_id' => User::factory(),
            'started_at' => now(),
            'submitted_at' => null,
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
            'result_type' => null,
            'dimension_scores' => null,
        ];
    }

    /**
     * Indicate that the attempt has been completed with a given result.
     *
     * @param  array<string, int>|null  $dimensionScores
     */
    public function completed(string $resultType = 'INTJ', ?array $dimensionScores = null): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now(),
            'status' => AssessmentAttempt::STATUS_COMPLETED,
            'result_type' => $resultType,
            'dimension_scores' => $dimensionScores ?? ['EI' => 18, 'SN' => 30, 'TF' => 22, 'JP' => 26],
        ]);
    }
}
