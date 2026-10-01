<?php

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Services\AssessmentAttemptService;
use Livewire\Volt\Volt;

function assessmentForSubmitEdgeCases(): Assessment
{
    $assessment = Assessment::factory()->published()->create();
    foreach (AssessmentQuestion::DIMENSIONS as $dimension) {
        AssessmentQuestion::factory()->for($assessment)->dimension($dimension)->count(8)->create();
    }

    return $assessment;
}

test('submitting is refused, not crashed, when an answer is a nested array', function () {
    $user = User::factory()->create();
    $assessment = assessmentForSubmitEdgeCases();
    $answers = $assessment->questions()->get()->mapWithKeys(fn ($q) => [$q->id => '3'])->all();
    $answers[array_key_first($answers)] = ['x' => '3'];
    $this->actingAs($user);

    Volt::test('assessments.attempt', ['assessment' => $assessment])
        ->set('answers', $answers)
        ->call('submit')
        ->assertDispatched('toast-show');

    expect(AssessmentAttempt::first()->status)->toBe(AssessmentAttempt::STATUS_IN_PROGRESS);
});

test('a refusal from the service shows a toast and keeps the attempt in progress', function () {
    $user = User::factory()->create();
    $assessment = assessmentForSubmitEdgeCases();
    $answers = $assessment->questions()->get()->mapWithKeys(fn ($q) => [$q->id => '3'])->all();
    $this->actingAs($user);

    $this->mock(AssessmentAttemptService::class, function ($mock) {
        $mock->shouldReceive('startOrResume')->andReturnUsing(fn ($a, $u) => AssessmentAttempt::create([
            'assessment_id' => $a->id, 'user_id' => $u->id, 'started_at' => now(), 'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
        ]));
        $mock->shouldReceive('submit')->andThrow(new DomainException('Ditolak service'));
    });

    Volt::test('assessments.attempt', ['assessment' => $assessment])
        ->set('answers', $answers)
        ->call('submit')
        ->assertDispatched('toast-show');

    expect(AssessmentAttempt::first()->status)->toBe(AssessmentAttempt::STATUS_IN_PROGRESS);
});
