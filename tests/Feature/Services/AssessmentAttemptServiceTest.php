<?php

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Services\AssessmentAttemptService;

/**
 * Assessment published dengan 8 pernyataan per dimensi (32 total), sama kayak struktur
 * OEJTS asli.
 */
function createPublishedAssessmentWithQuestions(): Assessment
{
    $assessment = Assessment::factory()->published()->create();

    foreach (AssessmentQuestion::DIMENSIONS as $dimension) {
        AssessmentQuestion::factory()->for($assessment)->dimension($dimension)->count(8)->create();
    }

    return $assessment;
}

test('starting an attempt creates one in progress', function () {
    $user = User::factory()->create();
    $assessment = createPublishedAssessmentWithQuestions();
    $service = app(AssessmentAttemptService::class);

    $attempt = $service->startOrResume($assessment, $user);

    expect($attempt->status)->toBe(AssessmentAttempt::STATUS_IN_PROGRESS);
    expect(AssessmentAttempt::where('assessment_id', $assessment->id)->where('user_id', $user->id)->count())->toBe(1);
});

test('starting an attempt twice resumes the same one', function () {
    $user = User::factory()->create();
    $assessment = createPublishedAssessmentWithQuestions();
    $service = app(AssessmentAttemptService::class);

    $first = $service->startOrResume($assessment, $user);
    $second = $service->startOrResume($assessment, $user);

    expect($first->id)->toBe($second->id);
    expect(AssessmentAttempt::count())->toBe(1);
});

test('an answer is saved for the question it belongs to', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $question = $assessment->questions()->first();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $service->saveAnswer($attempt, $question->id, 4);

    $answer = $attempt->answers()->first();
    expect($answer->assessment_question_id)->toBe($question->id);
    expect($answer->value)->toBe(4);
});

test('changing an answer updates the same saved row', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $question = $assessment->questions()->first();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $service->saveAnswer($attempt, $question->id, 2);
    $service->saveAnswer($attempt, $question->id, 5);

    expect($attempt->answers)->toHaveCount(1);
    expect($attempt->answers()->first()->value)->toBe(5);
});

test('a value outside the 1-5 range is not saved', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $question = $assessment->questions()->first();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $service->saveAnswer($attempt, $question->id, 0);
    $service->saveAnswer($attempt, $question->id, 6);

    expect($attempt->answers)->toHaveCount(0);
});

test('a question from another assessment is not saved', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $otherAssessment = createPublishedAssessmentWithQuestions();
    $foreignQuestion = $otherAssessment->questions()->first();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $service->saveAnswer($attempt, $foreignQuestion->id, 3);

    expect($attempt->answers)->toHaveCount(0);
});

test('a completed attempt can no longer change its saved answers', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $question = $assessment->questions()->first();
    $attempt = AssessmentAttempt::factory()->for($assessment)->completed('INTJ')->create();
    $service = app(AssessmentAttemptService::class);

    $service->saveAnswer($attempt, $question->id, 3);

    expect($attempt->answers)->toHaveCount(0);
});

test('submitting all extreme-low answers scores ESTJ', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $answers = $assessment->questions()->get()->mapWithKeys(fn ($question) => [$question->id => 1])->all();
    $result = $service->submit($attempt, $answers);

    expect($result->status)->toBe(AssessmentAttempt::STATUS_COMPLETED);
    expect($result->result_type)->toBe('ESTJ');
    expect($result->dimension_scores)->toBe(['EI' => 8, 'SN' => 8, 'TF' => 8, 'JP' => 8]);
});

test('submitting all extreme-high answers scores INFP', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $answers = $assessment->questions()->get()->mapWithKeys(fn ($question) => [$question->id => 5])->all();
    $result = $service->submit($attempt, $answers);

    expect($result->result_type)->toBe('INFP');
    expect($result->dimension_scores)->toBe(['EI' => 40, 'SN' => 40, 'TF' => 40, 'JP' => 40]);
});

test('submit credits every answer to the right dimension and stores scores that survive a reload', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $patterns = [
        'EI' => [5, 5, 5, 5, 1, 1, 1, 1], // 24 -> I
        'SN' => [1, 1, 1, 1, 5, 5, 5, 4], // 23 -> S
        'TF' => [3, 3, 3, 3, 3, 3, 3, 3], // 24 -> F
        'JP' => [2, 2, 2, 2, 2, 2, 2, 2], // 16 -> J
    ];

    $answers = [];

    foreach ($patterns as $dimension => $pattern) {
        $questions = $assessment->questions()->where('dimension', $dimension)->get()->values();

        foreach ($pattern as $index => $value) {
            $answers[$questions[$index]->id] = $value;
        }
    }

    $service->submit($attempt, $answers);

    // Dibaca ulang dari DB. MySQL nyimpen JSON dengan urutan key yang diurutin ulang
    // (EI, JP, SN, TF), jadi dicek per key, bukan per urutan.
    $stored = AssessmentAttempt::find($attempt->id);

    expect($stored->result_type)->toBe('ISFJ');
    expect($stored->dimension_scores['EI'])->toBe(24);
    expect($stored->dimension_scores['SN'])->toBe(23);
    expect($stored->dimension_scores['TF'])->toBe(24);
    expect($stored->dimension_scores['JP'])->toBe(16);
});

test('submitting with an unanswered question is refused and stores nothing', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $answers = $assessment->questions()->get()->mapWithKeys(fn ($question) => [$question->id => 3])->all();
    array_pop($answers);

    expect(fn () => app(AssessmentAttemptService::class)->submit($attempt, $answers))
        ->toThrow(DomainException::class, 'Semua pernyataan harus dijawab');

    $stored = $attempt->fresh();

    expect($stored->status)->toBe(AssessmentAttempt::STATUS_IN_PROGRESS);
    expect($stored->result_type)->toBeNull();
    expect($stored->answers)->toHaveCount(0);
});

test('submitting with a value outside 1-5 is refused', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $answers = $assessment->questions()->get()->mapWithKeys(fn ($question) => [$question->id => 3])->all();
    $answers[array_key_first($answers)] = 9;

    expect(fn () => app(AssessmentAttemptService::class)->submit($attempt, $answers))->toThrow(DomainException::class);
    expect($attempt->fresh()->status)->toBe(AssessmentAttempt::STATUS_IN_PROGRESS);
});

test('submit falls back to previously autosaved answers for questions not resent', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $assessment->questions()->get()->each(fn ($question) => $service->saveAnswer($attempt, $question->id, 5));

    $result = $service->submit($attempt, []);

    expect($result->status)->toBe(AssessmentAttempt::STATUS_COMPLETED);
    expect($result->answers()->where('value', 5)->count())->toBe(32);
});

test('submitting with an empty string for a question does not discard its saved answer', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $question = $assessment->questions()->first();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $assessment->questions()->get()->each(fn ($item) => $service->saveAnswer($attempt, $item->id, 5));
    $result = $service->submit($attempt, [$question->id => '']);

    $answer = $result->answers()->where('assessment_question_id', $question->id)->first();
    expect($answer->value)->toBe(5);
});

test('submit is idempotent once the attempt is completed', function () {
    $assessment = createPublishedAssessmentWithQuestions();
    $attempt = AssessmentAttempt::factory()->for($assessment)->create();
    $service = app(AssessmentAttemptService::class);

    $lowAnswers = $assessment->questions()->get()->mapWithKeys(fn ($question) => [$question->id => 1])->all();
    $first = $service->submit($attempt, $lowAnswers);

    $highAnswers = $assessment->questions()->get()->mapWithKeys(fn ($question) => [$question->id => 5])->all();
    $second = $service->submit($attempt, $highAnswers);

    expect($first->result_type)->toBe('ESTJ');
    expect($second->result_type)->toBe('ESTJ');
    expect($attempt->fresh()->answers)->toHaveCount(32);
});
