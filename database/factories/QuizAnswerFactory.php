<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAnswer>
 */
class QuizAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_attempt_id' => QuizAttempt::factory(),
            'question_id' => Question::factory(),
            'selected_option_id' => null,
            'is_correct' => false,
            'points_awarded' => 0,
        ];
    }
}
