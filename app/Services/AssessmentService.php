<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\User;

class AssessmentService
{
    /**
     * @param  array{title: string, description: ?string}  $data
     */
    public function create(User $creator, array $data): Assessment
    {
        $assessment = new Assessment($data);
        $assessment->created_by = $creator->id;
        $assessment->status = Assessment::STATUS_DRAFT;
        $assessment->save();

        return $assessment;
    }

    /**
     * @param  array{title: string, description: ?string}  $data
     */
    public function update(Assessment $assessment, array $data): Assessment
    {
        $assessment->update($data);

        return $assessment;
    }

    public function delete(Assessment $assessment): void
    {
        $assessment->delete();
    }

    public function togglePublish(Assessment $assessment): Assessment
    {
        if (!$assessment->isPublished()) {
            $this->assertReadyToPublish($assessment);
        }

        $assessment->update([
            'status' => $assessment->isPublished() ? Assessment::STATUS_DRAFT : Assessment::STATUS_PUBLISHED,
        ]);

        return $assessment;
    }

    /**
     * Skoring OEJTS-nya fix (8 pernyataan per dimensi), jadi beda dari Quiz yang cuma
     * butuh minimal 1 soal — instrumen ini wajib pas 32 pernyataan, 8-8-8-8 per dimensi,
     * baru boleh dipublish.
     *
     * @throws \DomainException
     */
    private function assertReadyToPublish(Assessment $assessment): void
    {
        // reorder() buang ORDER BY order bawaan relasi questions(), soalnya kalau nggak,
        // GROUP BY dimension konflik sama ORDER BY order di MySQL strict mode
        // (ONLY_FULL_GROUP_BY). SQLite (DB test) gak strict soal ini, jadi baru ketauan
        // pas dicoba manual ke MySQL beneran, bukan lewat test suite.
        $counts = $assessment->questions()
            ->reorder()
            ->selectRaw('dimension, count(*) as total')
            ->groupBy('dimension')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->dimension => (int) $row->total]);

        $totalQuestions = $counts->sum();

        if ($totalQuestions !== 32) {
            throw new \DomainException("Instrumen ini butuh tepat 32 pernyataan sebelum bisa dipublish (sekarang: {$totalQuestions}).");
        }

        foreach (AssessmentQuestion::DIMENSIONS as $dimension) {
            $dimensionTotal = $counts[$dimension] ?? 0;

            if ($dimensionTotal !== 8) {
                throw new \DomainException("Dimensi {$dimension} butuh tepat 8 pernyataan (sekarang: {$dimensionTotal}).");
            }
        }
    }
}
