<?php

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Services\AssessmentQuestionService;

test('create appends a question at the end of the current order', function () {
    $assessment = Assessment::factory()->create();
    AssessmentQuestion::factory()->for($assessment)->create(['order' => 0]);
    $service = app(AssessmentQuestionService::class);

    $question = $service->create($assessment, [
        'dimension' => 'EI',
        'statement_left' => 'Suka bikin daftar',
        'statement_right' => 'Mengandalkan ingatan',
    ]);

    expect($question->order)->toBe(1);
    expect($question->dimension)->toBe('EI');
});

test('update changes the statements and dimension', function () {
    $question = AssessmentQuestion::factory()->dimension('EI')->create();
    $service = app(AssessmentQuestionService::class);

    $service->update($question, [
        'dimension' => 'SN',
        'statement_left' => 'Kiri baru',
        'statement_right' => 'Kanan baru',
    ]);

    expect($question->fresh()->dimension)->toBe('SN');
    expect($question->fresh()->statement_left)->toBe('Kiri baru');
});

test('delete is blocked while the parent assessment is published', function () {
    $assessment = Assessment::factory()->published()->create();
    $question = AssessmentQuestion::factory()->for($assessment)->create();
    $service = app(AssessmentQuestionService::class);

    expect(fn () => $service->delete($question))->toThrow(DomainException::class);
    expect(AssessmentQuestion::find($question->id))->not->toBeNull();
});

test('delete succeeds while the parent assessment is a draft', function () {
    $assessment = Assessment::factory()->draft()->create();
    $question = AssessmentQuestion::factory()->for($assessment)->create();
    $service = app(AssessmentQuestionService::class);

    $service->delete($question);

    expect(AssessmentQuestion::find($question->id))->toBeNull();
});

test('moveUp and moveDown swap order with the adjacent question', function () {
    $assessment = Assessment::factory()->create();
    $first = AssessmentQuestion::factory()->for($assessment)->create(['order' => 0]);
    $second = AssessmentQuestion::factory()->for($assessment)->create(['order' => 1]);
    $service = app(AssessmentQuestionService::class);

    $service->moveDown($first);

    expect($first->fresh()->order)->toBe(1);
    expect($second->fresh()->order)->toBe(0);

    $service->moveUp($first->fresh());

    expect($first->fresh()->order)->toBe(0);
    expect($second->fresh()->order)->toBe(1);
});

test('moveUp on the first question and moveDown on the last question are no-ops', function () {
    $assessment = Assessment::factory()->create();
    $first = AssessmentQuestion::factory()->for($assessment)->create(['order' => 0]);
    $last = AssessmentQuestion::factory()->for($assessment)->create(['order' => 1]);
    $service = app(AssessmentQuestionService::class);

    $service->moveUp($first);
    $service->moveDown($last);

    expect($first->fresh()->order)->toBe(0);
    expect($last->fresh()->order)->toBe(1);
});
