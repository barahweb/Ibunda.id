<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuizAttemptService
{
    public function startOrResume(Quiz $quiz, User $user): QuizAttempt
    {
        $existing = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->first();

        if ($existing) {
            return $existing;
        }

        return QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * @param  array<string, string>  $answers  question_id => selected_option_id
     */
    public function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $answers) {
            $totalPoints = 0;
            $earnedPoints = 0;

            foreach ($attempt->quiz->questions()->with('options')->get() as $question) {
                $selectedOptionId = $answers[$question->id] ?? null;

                // Opsi yang dipilih wajib benar-benar milik soal ini — kalau nggak, anggap
                // belum dijawab. Ini jaga-jaga terhadap payload yang dipalsukan (option_id
                // dari soal lain), UI normal nggak akan pernah kirim kombinasi kayak gini.
                if ($selectedOptionId !== null && ! $question->options->contains('id', $selectedOptionId)) {
                    $selectedOptionId = null;
                }

                $isCorrect = $selectedOptionId !== null
                    && $question->correctOption?->id === $selectedOptionId;

                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'selected_option_id' => $selectedOptionId,
                    'is_correct' => $isCorrect,
                    'points_awarded' => $isCorrect ? $question->points : 0,
                ]);

                $totalPoints += $question->points;
                $earnedPoints += $isCorrect ? $question->points : 0;
            }

            $score = $totalPoints > 0 ? (int) round(($earnedPoints / $totalPoints) * 100) : 0;

            $attempt->update([
                'submitted_at' => now(),
                'score' => $score,
                'status' => QuizAttempt::STATUS_COMPLETED,
            ]);

            return $attempt;
        });
    }
}
