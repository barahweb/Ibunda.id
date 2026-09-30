<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentQuestion>
 */
class AssessmentQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'dimension' => fake()->randomElement(AssessmentQuestion::DIMENSIONS),
            'statement_left' => fake()->words(3, true),
            'statement_right' => fake()->words(3, true),
            'order' => 0,
        ];
    }

    /**
     * Force the question into a specific dimension.
     */
    public function dimension(string $dimension): static
    {
        return $this->state(fn (array $attributes) => [
            'dimension' => $dimension,
        ]);
    }
}
