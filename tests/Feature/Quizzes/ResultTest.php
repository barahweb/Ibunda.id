<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Livewire\Volt\Volt;

/**
 * Creates a published quiz with one question (options: correct + wrong) and a completed
 * attempt by $user with the given score, plus one answer record for that question.
 */
function completedAttemptFor(User $user, int $score, ?int $passingScore = 70): QuizAttempt
{
    $quiz = Quiz::factory()->published()->create(['passing_score' => $passingScore]);
    $question = Question::factory()->for($quiz)->create();
    $correctOption = QuestionOption::factory()->for($question)->correct()->create(['option_text' => 'Benar']);
    QuestionOption::factory()->for($question)->create(['option_text' => 'Salah']);

    $attempt = QuizAttempt::factory()->for($quiz)->for($user)->completed($score)->create();

    QuizAnswer::factory()
        ->for($attempt, 'attempt')
        ->for($question, 'question')
        ->create([
            'selected_option_id' => $score === 100 ? $correctOption->id : null,
            'is_correct' => $score === 100,
            'points_awarded' => $score === 100 ? 1 : 0,
        ]);

    return $attempt;
}

test('guest is redirected to login for result, history, and submissions pages', function () {
    $attempt = completedAttemptFor(User::factory()->create(), 100);

    $this->get(route('quizzes.attempts.result', $attempt))->assertRedirect(route('login'));
    $this->get(route('quizzes.history'))->assertRedirect(route('login'));
    $this->get(route('admin.quizzes.submissions', $attempt->quiz))->assertRedirect(route('login'));
});

test('a participant can view their own result', function () {
    $participant = User::factory()->create();
    $attempt = completedAttemptFor($participant, 80);

    $response = $this->actingAs($participant)->get(route('quizzes.attempts.result', $attempt));

    $response->assertOk()->assertSee('80%');
});

test('a participant does not see the per-question answer breakdown', function () {
    $participant = User::factory()->create();
    $attempt = completedAttemptFor($participant, 0);

    $response = $this->actingAs($participant)->get(route('quizzes.attempts.result', $attempt));

    $response->assertOk()->assertDontSee('Jawaban benar:')->assertDontSee('Jawaban peserta:');
});

test('a participant cannot view someone else\'s result', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $attempt = completedAttemptFor($owner, 80);

    $response = $this->actingAs($intruder)->get(route('quizzes.attempts.result', $attempt));

    $response->assertForbidden();
});

test('an admin can view any participant\'s result', function () {
    $admin = User::factory()->admin()->create();
    $participant = User::factory()->create();
    $attempt = completedAttemptFor($participant, 80);

    $response = $this->actingAs($admin)->get(route('quizzes.attempts.result', $attempt));

    $response->assertOk();
});

test('an admin sees the per-question answer breakdown', function () {
    $admin = User::factory()->admin()->create();
    $participant = User::factory()->create();
    $attempt = completedAttemptFor($participant, 0);

    $response = $this->actingAs($admin)->get(route('quizzes.attempts.result', $attempt));

    $response->assertOk()->assertSee('Jawaban benar:')->assertSee('(tidak dijawab)');
});

test('the result page 404s for an attempt still in progress', function () {
    $participant = User::factory()->create();
    $quiz = Quiz::factory()->published()->create();
    $attempt = QuizAttempt::factory()->for($quiz)->for($participant)->create();

    $response = $this->actingAs($participant)->get(route('quizzes.attempts.result', $attempt));

    $response->assertNotFound();
});

test('a participant only sees their own attempts in history', function () {
    $participant = User::factory()->create();
    $other = User::factory()->create();
    $ownAttempt = completedAttemptFor($participant, 90);
    completedAttemptFor($other, 50);

    $response = $this->actingAs($participant)->get(route('quizzes.history'));

    $response->assertOk()->assertSee($ownAttempt->quiz->title)->assertSee('90%');
    expect(QuizAttempt::where('user_id', $participant->id)->count())->toBe(1);
});

test('participants cannot access the admin submissions page', function () {
    $participant = User::factory()->create();
    $attempt = completedAttemptFor($participant, 90);

    $response = $this->actingAs($participant)->get(route('admin.quizzes.submissions', $attempt->quiz));

    $response->assertForbidden();
});

test('admin sees all submissions and correct aggregate stats for a quiz', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quiz = Quiz::factory()->for($admin, 'creator')->published()->create(['passing_score' => 70]);

    $passer = User::factory()->create(['name' => 'Peserta Lulus']);
    $failer = User::factory()->create(['name' => 'Peserta Gagal']);

    QuizAttempt::factory()->for($quiz)->for($passer)->completed(80)->create();
    QuizAttempt::factory()->for($quiz)->for($failer)->completed(40)->create();

    Volt::test('admin.quizzes.submissions', ['quiz' => $quiz])
        ->assertOk()
        ->assertSee('Peserta Lulus')
        ->assertSee('Peserta Gagal')
        ->assertSee('80%')
        ->assertSee('40%');

    $stats = Volt::test('admin.quizzes.submissions', ['quiz' => $quiz])->get('stats');

    expect($stats['total'])->toBe(2);
    expect($stats['average_score'])->toBe(60.0);
    expect($stats['passed'])->toBe(1);
});
