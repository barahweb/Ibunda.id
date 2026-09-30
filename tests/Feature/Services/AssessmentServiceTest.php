<?php

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Services\AssessmentService;

function seedPublishReadyAssessment(): Assessment
{
    $assessment = Assessment::factory()->draft()->create();

    foreach (AssessmentQuestion::DIMENSIONS as $dimension) {
        AssessmentQuestion::factory()->for($assessment)->dimension($dimension)->count(8)->create();
    }

    return $assessment;
}

test('create sets the creator and defaults to draft', function () {
    $admin = User::factory()->admin()->create();
    $service = app(AssessmentService::class);

    $assessment = $service->create($admin, ['title' => 'Tes Kepribadian', 'description' => 'Deskripsi']);

    expect($assessment->created_by)->toBe($admin->id);
    expect($assessment->status)->toBe(Assessment::STATUS_DRAFT);
    expect($assessment->title)->toBe('Tes Kepribadian');
});

test('update changes the title and description', function () {
    $assessment = Assessment::factory()->create(['title' => 'Lama']);
    $service = app(AssessmentService::class);

    $service->update($assessment, ['title' => 'Baru', 'description' => 'Deskripsi baru']);

    expect($assessment->fresh()->title)->toBe('Baru');
});

test('delete removes the assessment', function () {
    $assessment = Assessment::factory()->create();
    $service = app(AssessmentService::class);

    $service->delete($assessment);

    expect(Assessment::find($assessment->id))->toBeNull();
});

test('publishing fails when the assessment does not have exactly 32 questions', function () {
    $empty = Assessment::factory()->draft()->create();
    $service = app(AssessmentService::class);

    expect(fn () => $service->togglePublish($empty))
        ->toThrow(DomainException::class, 'Instrumen ini butuh tepat 32 pernyataan sebelum bisa dipublish (sekarang: 0).');

    expect($empty->fresh()->status)->toBe(Assessment::STATUS_DRAFT);
});

test('publishing fails when a dimension does not have exactly 8 questions', function () {
    $assessment = Assessment::factory()->draft()->create();
    AssessmentQuestion::factory()->for($assessment)->dimension('EI')->count(9)->create();
    AssessmentQuestion::factory()->for($assessment)->dimension('SN')->count(8)->create();
    AssessmentQuestion::factory()->for($assessment)->dimension('TF')->count(8)->create();
    AssessmentQuestion::factory()->for($assessment)->dimension('JP')->count(7)->create();
    $service = app(AssessmentService::class);

    expect(fn () => $service->togglePublish($assessment))
        ->toThrow(DomainException::class, 'Dimensi EI butuh tepat 8 pernyataan (sekarang: 9).');
});

test('publishing succeeds with exactly 32 questions split 8-8-8-8 across dimensions', function () {
    $assessment = seedPublishReadyAssessment();
    $service = app(AssessmentService::class);

    $service->togglePublish($assessment);

    expect($assessment->fresh()->status)->toBe(Assessment::STATUS_PUBLISHED);
});

test('unpublishing a published assessment never checks the question count', function () {
    $assessment = seedPublishReadyAssessment();
    $service = app(AssessmentService::class);
    $service->togglePublish($assessment);

    $service->togglePublish($assessment);

    expect($assessment->fresh()->status)->toBe(Assessment::STATUS_DRAFT);
});
