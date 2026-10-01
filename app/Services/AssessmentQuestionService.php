<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;

class AssessmentQuestionService
{
    private const PUBLISHED_GUARD_MESSAGE = 'Assessment ini sudah published dan skoringnya butuh tepat 32 pernyataan (8 per dimensi). Unpublish dulu sebelum mengubah pernyataan.';

    /**
     * @param  array{dimension: string, statement_left: string, statement_right: string}  $data
     *
     * @throws \DomainException
     */
    public function create(Assessment $assessment, array $data): AssessmentQuestion
    {
        if ($assessment->isPublished()) {
            throw new \DomainException(self::PUBLISHED_GUARD_MESSAGE);
        }

        // Pakai max(order)+1, bukan count(): kalau soal di tengah dihapus (soft delete),
        // count() bikin soal baru dapat order yang sama kayak soal terakhir yang masih ada.
        $lastOrder = $assessment->questions()->reorder()->max('order');
        $nextOrder = $lastOrder === null ? 0 : ((int) $lastOrder) + 1;

        return $assessment->questions()->create([
            'dimension' => $data['dimension'],
            'statement_left' => $data['statement_left'],
            'statement_right' => $data['statement_right'],
            'order' => $nextOrder,
        ]);
    }

    /**
     * @param  array{dimension: string, statement_left: string, statement_right: string}  $data
     *
     * @throws \DomainException
     */
    public function update(AssessmentQuestion $question, array $data): AssessmentQuestion
    {
        if ($question->assessment->isPublished()) {
            throw new \DomainException(self::PUBLISHED_GUARD_MESSAGE);
        }

        $question->update($data);

        return $question;
    }

    /**
     * @throws \DomainException
     */
    public function delete(AssessmentQuestion $question): void
    {
        if ($question->assessment->isPublished()) {
            throw new \DomainException(self::PUBLISHED_GUARD_MESSAGE);
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
