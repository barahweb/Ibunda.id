<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\User;

class QuizService
{
    /**
     * @param  array{title: string, description: ?string, time_limit_minutes: ?int, passing_score: ?int}  $data
     */
    public function create(User $creator, array $data): Quiz
    {
        $quiz = new Quiz($data);
        $quiz->created_by = $creator->id;
        $quiz->status = Quiz::STATUS_DRAFT;
        $quiz->save();

        return $quiz;
    }

    /**
     * @param  array{title: string, description: ?string, time_limit_minutes: ?int, passing_score: ?int}  $data
     */
    public function update(Quiz $quiz, array $data): Quiz
    {
        $quiz->update($data);

        return $quiz;
    }

    public function delete(Quiz $quiz): void
    {
        $quiz->delete();
    }

    public function togglePublish(Quiz $quiz): Quiz
    {
        $quiz->update([
            'status' => $quiz->isPublished() ? Quiz::STATUS_DRAFT : Quiz::STATUS_PUBLISHED,
        ]);

        return $quiz;
    }
}
