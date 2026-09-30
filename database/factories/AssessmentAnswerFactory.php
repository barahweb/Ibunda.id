<?php

namespace Database\Factories;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAnswer>
 */
class AssessmentAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_attempt_id' => AssessmentAttempt::factory(),
            'assessment_question_id' => AssessmentQuestion::factory(),
            'value' => fake()->numberBetween(1, 5),
        ];
    }
}
