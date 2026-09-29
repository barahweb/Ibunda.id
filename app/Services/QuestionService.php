<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;

class QuestionService
{
    /**
     * @param  array{question_text: string, points: int, options: array<int, string>, correct_index: int}  $data
     */
    public function create(Quiz $quiz, array $data): Question
    {
        return DB::transaction(function () use ($quiz, $data) {
            $question = $quiz->questions()->create([
                'question_text' => $data['question_text'],
                'points' => $data['points'],
                'order' => $quiz->questions()->count(),
            ]);

            $this->syncOptions($question, $data['options'], $data['correct_index']);

            return $question;
        });
    }

    /**
     * @param  array{question_text: string, points: int, options: array<int, string>, correct_index: int}  $data
     */
    public function update(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data) {
            $question->update([
                'question_text' => $data['question_text'],
                'points' => $data['points'],
            ]);

            $question->options()->delete();
            $this->syncOptions($question, $data['options'], $data['correct_index']);

            return $question;
        });
    }

    public function delete(Question $question): void
    {
        if ($question->quiz->isPublished() && $question->quiz->questions()->count() === 1) {
            throw new \DomainException('Ini satu-satunya soal di quiz yang sudah published. Unpublish dulu quiz-nya kalau mau menghapus soal ini.');
        }

        // Question & QuestionOption pakai SoftDeletes, jadi ini nggak beneran menghapus baris-nya
        // dari database — quiz_answers lama yang masih nunjuk ke soal/opsi ini tetap valid
        // (lihat QuestionManagementTest: "deleting a question preserves the historical answer").
        $question->delete();
    }

    public function moveUp(Question $question): void
    {
        $previous = Question::where('quiz_id', $question->quiz_id)
            ->where('order', '<', $question->order)
            ->orderByDesc('order')
            ->first();

        if ($previous) {
            $this->swapOrder($question, $previous);
        }
    }

    public function moveDown(Question $question): void
    {
        $next = Question::where('quiz_id', $question->quiz_id)
            ->where('order', '>', $question->order)
            ->orderBy('order')
            ->first();

        if ($next) {
            $this->swapOrder($question, $next);
        }
    }

    /**
     * @param  array<int, string>  $options
     */
    private function syncOptions(Question $question, array $options, int $correctIndex): void
    {
        foreach (array_values($options) as $index => $text) {
            $question->options()->create([
                'option_text' => $text,
                'is_correct' => $index === $correctIndex,
                'order' => $index,
            ]);
        }
    }

    private function swapOrder(Question $a, Question $b): void
    {
        [$orderA, $orderB] = [$a->order, $b->order];

        $a->update(['order' => $orderB]);
        $b->update(['order' => $orderA]);
    }
}
