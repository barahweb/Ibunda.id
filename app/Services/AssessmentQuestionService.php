<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;

class AssessmentQuestionService
{
    /**
     * @param  array{dimension: string, statement_left: string, statement_right: string}  $data
     */
    public function create(Assessment $assessment, array $data): AssessmentQuestion
    {
        return $assessment->questions()->create([
            'dimension' => $data['dimension'],
            'statement_left' => $data['statement_left'],
            'statement_right' => $data['statement_right'],
            'order' => $assessment->questions()->count(),
        ]);
    }

    /**
     * @param  array{dimension: string, statement_left: string, statement_right: string}  $data
     */
    public function update(AssessmentQuestion $question, array $data): AssessmentQuestion
    {
        $question->update($data);

        return $question;
    }

    /**
     * @throws \DomainException
     */
    public function delete(AssessmentQuestion $question): void
    {
        if ($question->assessment->isPublished()) {
            throw new \DomainException('Assessment ini sudah published dan skoringnya butuh tepat 32 pernyataan (8 per dimensi). Unpublish dulu sebelum menghapus pernyataan.');
        }

        $question->delete();
    }

    public function moveUp(AssessmentQuestion $question): void
    {
        $previous = AssessmentQuestion::where('assessment_id', $question->assessment_id)
            ->where('order', '<', $question->order)
            ->orderByDesc('order')
            ->first();

        if ($previous) {
            $this->swapOrder($question, $previous);
        }
    }

    public function moveDown(AssessmentQuestion $question): void
    {
        $next = AssessmentQuestion::where('assessment_id', $question->assessment_id)
            ->where('order', '>', $question->order)
            ->orderBy('order')
            ->first();

        if ($next) {
            $this->swapOrder($question, $next);
        }
    }

    private function swapOrder(AssessmentQuestion $a, AssessmentQuestion $b): void
    {
        [$orderA, $orderB] = [$a->order, $b->order];

        $a->update(['order' => $orderB]);
        $b->update(['order' => $orderA]);
    }
}
