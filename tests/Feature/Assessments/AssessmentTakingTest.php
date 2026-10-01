<?php

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

function publishedAssessmentForTaking(): Assessment
{
    $assessment = Assessment::factory()->published()->create(['title' => 'Tes OEJTS']);

    foreach (AssessmentQuestion::DIMENSIONS as $dimension) {
        AssessmentQuestion::factory()->for($assessment)->dimension($dimension)->count(8)->create();
    }

    return $assessment;
}

function allAnswers(Assessment $assessment, int $value): array
{
    return $assessment->questions()->get()->mapWithKeys(fn ($question) => [$question->id => (string) $value])->all();
}

test('guest is redirected to login', function () {
    $assessment = publishedAssessmentForTaking();

    $this->get(route('assessments.index'))->assertRedirect(route('login'));
    $this->get(route('assessments.attempt', $assessment))->assertRedirect(route('login'));
});

test('the list only shows published assessments that have questions', function () {
    $participant = User::factory()->create();
    publishedAssessmentForTaking();
    Assessment::factory()->draft()->create(['title' => 'Tes Draft']);
    Assessment::factory()->published()->create(['title' => 'Tes Kosong']);

    $response = $this->actingAs($participant)->get(route('assessments.index'));

    $response->assertOk()->assertSee('Tes OEJTS')->assertDontSee('Tes Draft')->assertDontSee('Tes Kosong');
});

test('the credit for the open license is shown on the list and on the attempt page', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $this->actingAs($participant);

    $this->get(route('assessments.index'))->assertSee('Open Psychometrics')->assertSee('CC BY-NC-SA 4.0');
    $this->get(route('assessments.attempt', $assessment))->assertSee('Open Psychometrics')->assertSee('CC BY-NC-SA 4.0');
});

test('a participant cannot attempt a draft assessment', function () {
    $participant = User::factory()->create();
    $draft = Assessment::factory()->draft()->create();

    $this->actingAs($participant)->get(route('assessments.attempt', $draft))->assertForbidden();
});

test('a published assessment without questions cannot be attempted', function () {
    $participant = User::factory()->create();
    $empty = Assessment::factory()->published()->create();

    $this->actingAs($participant)->get(route('assessments.attempt', $empty))->assertNotFound();
    expect(AssessmentAttempt::count())->toBe(0);
});

test('visiting the attempt page starts one in-progress attempt and revisiting resumes it', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $this->actingAs($participant);

    $this->get(route('assessments.attempt', $assessment))->assertOk();
    $this->get(route('assessments.attempt', $assessment))->assertOk();

    expect(AssessmentAttempt::where('assessment_id', $assessment->id)->where('user_id', $participant->id)->count())->toBe(1);
});

test('each participant always gets their own attempt', function () {
    $assessment = publishedAssessmentForTaking();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first)->get(route('assessments.attempt', $assessment));
    $this->actingAs($second)->get(route('assessments.attempt', $assessment));

    expect(AssessmentAttempt::where('user_id', $first->id)->count())->toBe(1);
    expect(AssessmentAttempt::where('user_id', $second->id)->count())->toBe(1);
    expect(AssessmentAttempt::count())->toBe(2);
});

test('picking a value is saved right away and restored when the page is reopened', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $question = $assessment->questions()->first();
    $this->actingAs($participant);

    Volt::test('assessments.attempt', ['assessment' => $assessment])->call('saveAnswer', $question->id, 4);

    Volt::test('assessments.attempt', ['assessment' => $assessment])
        ->assertSet('answers', [$question->id => '4']);
});

test('submitting is refused until every statement is answered', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $this->actingAs($participant);

    $answers = allAnswers($assessment, 1);
    array_pop($answers);

    Volt::test('assessments.attempt', ['assessment' => $assessment])
        ->set('answers', $answers)
        ->call('submit')
        ->assertDispatched('toast-show');

    expect(AssessmentAttempt::first()->status)->toBe(AssessmentAttempt::STATUS_IN_PROGRESS);
});

test('submitting every statement completes the attempt with a result type', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $this->actingAs($participant);

    Volt::test('assessments.attempt', ['assessment' => $assessment])
        ->set('answers', allAnswers($assessment, 5))
        ->call('submit')
        ->assertRedirect(route('assessments.attempts.result', ['attempt' => AssessmentAttempt::first(), 'baru' => 1]));

    $attempt = AssessmentAttempt::first();

    expect($attempt->status)->toBe(AssessmentAttempt::STATUS_COMPLETED);
    expect($attempt->result_type)->toBe('INFP');
    expect($attempt->answers)->toHaveCount(32);
});

test('a completed attempt cannot be submitted again', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $this->actingAs($participant);

    $component = Volt::test('assessments.attempt', ['assessment' => $assessment])
        ->set('answers', allAnswers($assessment, 1))
        ->call('submit');

    $component->set('answers', allAnswers($assessment, 5))->call('submit');

    expect(AssessmentAttempt::first()->result_type)->toBe('ESTJ');
});

test('rapid repeated submit calls are rate limited', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $this->actingAs($participant);

    $component = Volt::test('assessments.attempt', ['assessment' => $assessment]);

    for ($i = 0; $i < 5; $i++) {
        $component->call('submit');
    }

    $component->set('answers', allAnswers($assessment, 5))->call('submit');

    expect(AssessmentAttempt::first()->status)->toBe(AssessmentAttempt::STATUS_IN_PROGRESS);
});

test('submitting is refused when an answer is outside the 1-5 scale', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $this->actingAs($participant);

    $answers = allAnswers($assessment, 5);
    $answers[array_key_first($answers)] = '9';

    Volt::test('assessments.attempt', ['assessment' => $assessment])
        ->set('answers', $answers)
        ->call('submit')
        ->assertDispatched('toast-show');

    expect(AssessmentAttempt::first()->status)->toBe(AssessmentAttempt::STATUS_IN_PROGRESS);
});

test('a saved answer for a question that was deleted is not restored on the page', function () {
    $participant = User::factory()->create();
    $assessment = publishedAssessmentForTaking();
    $question = $assessment->questions()->first();
    $this->actingAs($participant);

    Volt::test('assessments.attempt', ['assessment' => $assessment])->call('saveAnswer', $question->id, 4);
    $question->delete();

    Volt::test('assessments.attempt', ['assessment' => $assessment])->assertSet('answers', []);
});

test('unknown or malformed assessment ids return 404 instead of an error', function () {
    $participant = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($participant);
    $this->get(route('assessments.attempt', (string) Str::uuid()))->assertNotFound();
    $this->get(route('assessments.attempt', 'bukan-uuid'))->assertNotFound();

    $this->actingAs($admin);
    $this->get(route('admin.assessments.questions', (string) Str::uuid()))->assertNotFound();
    $this->get(route('admin.assessments.questions', 'bukan-uuid'))->assertNotFound();
});

test('an admin can open the attempt page of a draft assessment but a participant cannot', function () {
    $draft = Assessment::factory()->draft()->create();
    AssessmentQuestion::factory()->for($draft)->create();

    $this->actingAs(User::factory()->admin()->create())->get(route('assessments.attempt', $draft))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('assessments.attempt', $draft))->assertForbidden();
});
