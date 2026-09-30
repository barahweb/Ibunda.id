<?php

namespace App\Helper;

use App\Models\AssessmentQuestion;
use Illuminate\Support\Collection;

final class OejtsScorer
{
    public const DIMENSIONS = ['EI', 'SN', 'TF', 'JP'];

    public const THRESHOLD = 24;

    /**
     * @param  Collection<int, AssessmentQuestion>  $questions
     * @param  array<string, int|null>  $valuesByQuestionId  question_id => nilai Likert 1-5 (atau null kalau gak dijawab)
     * @return array<string, int>
     */
    public static function sumsByDimension(Collection $questions, array $valuesByQuestionId): array
    {
        $sums = array_fill_keys(self::DIMENSIONS, 0);

        foreach ($questions as $question) {
            $value = $valuesByQuestionId[$question->id] ?? null;

            if ($value !== null) {
                $sums[$question->dimension] += $value;
            }
        }

        return $sums;
    }

    /**
     * @param  array<string, int>  $dimensionSums
     */
    public static function resolveResultType(array $dimensionSums): string
    {
        $type = '';

        foreach (self::DIMENSIONS as $dimension) {
            $type .= self::letterForDimension($dimension, $dimensionSums[$dimension] ?? 0);
        }

        return $type;
    }

    public static function letterForDimension(string $dimension, int $sum): string
    {
        return $sum < self::THRESHOLD ? substr($dimension, 0, 1) : substr($dimension, 1, 1);
    }
}
