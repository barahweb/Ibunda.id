<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuestionService;
use Livewire\Volt\Volt;

test('guest is redirected to login', function () {
    $quiz = Quiz::factory()->create();

    $response = $this->get(route('admin.quizzes.questions', $quiz));

    $response->assertRedirect(route('login'));
});

test('participant cannot access question management', function () {
    $participant = User::factory()->create();
    $quiz = Quiz::factory()->create();

    $response = $this->actingAs($participant)->get(route('admin.quizzes.questions', $quiz));

    $response->assertForbidden();
});

test('admin can view questions for a quiz', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $question = Question::factory()->for($quiz)->create(['question_text' => 'Ibukota Indonesia?']);
    QuestionOption::factory()->for($question)->correct()->create(['option_text' => 'Jakarta']);

    $response = $this->actingAs($admin)->get(route('admin.quizzes.questions', $quiz));

    $response->assertOk()->assertSee('Ibukota Indonesia?')->assertSee('Jakarta');
});

test('admin can create a question with options', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $this->actingAs($admin);

    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])
        ->call('createQuestion')
        ->set('questionText', 'Ibukota Indonesia?')
        ->set('points', 2)
        ->set('options', ['Jakarta', 'Surabaya', 'Bandung', 'Medan'])
        ->set('correctIndex', 0)
        ->call('save')
        ->assertHasNoErrors();

    $question = Question::where('question_text', 'Ibukota Indonesia?')->first();

    expect($question)->not->toBeNull();
    expect($question->points)->toBe(2);
    expect($question->options)->toHaveCount(4);
    expect($question->options->firstWhere('option_text', 'Jakarta')->is_correct)->toBeTrue();
    expect($question->options->firstWhere('option_text', 'Surabaya')->is_correct)->toBeFalse();
});

test('at least two options are required', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $this->actingAs($admin);

    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])
        ->call('createQuestion')
        ->set('questionText', 'Soal tanpa opsi cukup')
        ->set('options', ['Cuma satu'])
        ->call('save')
        ->assertHasErrors(['options']);
});

test('correct index must point to a real option', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $this->actingAs($admin);

    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])
        ->call('createQuestion')
        ->set('questionText', 'Soal dengan correctIndex salah')
        ->set('options', ['A', 'B'])
        ->set('correctIndex', 5)
        ->call('save')
        ->assertHasErrors(['correctIndex']);
});

test('admin can edit a question and its options', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $question = Question::factory()->for($quiz)->create(['question_text' => 'Judul lama']);
    QuestionOption::factory()->for($question)->correct()->create(['option_text' => 'Benar']);
    QuestionOption::factory()->for($question)->create(['option_text' => 'Salah']);
    $this->actingAs($admin);

    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('questionText', 'Judul baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($question->refresh()->question_text)->toBe('Judul baru');
    expect($question->options)->toHaveCount(2);
});

test('admin can delete a question', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $question = Question::factory()->for($quiz)->create();
    $this->actingAs($admin);

    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])->call('deleteQuestion', $question->id);

    expect(Question::find($question->id))->toBeNull();
});

test('the last question of a published quiz cannot be deleted', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->published()->create();
    $question = Question::factory()->for($quiz)->create();
    $this->actingAs($admin);

    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])
        ->call('deleteQuestion', $question->id)
        ->assertDispatched('toast-show');

    expect(Question::find($question->id))->not->toBeNull();
});

test('a non-last question of a published quiz can still be deleted', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->published()->create();
    $first = Question::factory()->for($quiz)->create();
    $second = Question::factory()->for($quiz)->create();
    $this->actingAs($admin);

    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])
        ->call('deleteQuestion', $first->id)
        ->assertHasNoErrors();

    expect(Question::find($first->id))->toBeNull();
    expect(Question::find($second->id))->not->toBeNull();
});

test('deleting a question preserves the historical answer that referenced it', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->published()->create();
    $answeredQuestion = Question::factory()->for($quiz)->create();
    $option = QuestionOption::factory()->for($answeredQuestion)->correct()->create();
    Question::factory()->for($quiz)->create(); // keeps the quiz above the "last question" guard

    $attempt = QuizAttempt::factory()->for($quiz)->completed(100)->create();
    $answer = QuizAnswer::factory()
        ->for($attempt, 'attempt')
        ->for($answeredQuestion, 'question')
        ->create(['selected_option_id' => $option->id, 'is_correct' => true]);

    $this->actingAs($admin);
    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])->call('deleteQuestion', $answeredQuestion->id);

    // Hilang dari query normal (soft-deleted)...
    expect(Question::find($answeredQuestion->id))->toBeNull();
    // ...tapi baris aslinya masih ada, dan jawaban lama nggak ikut rusak.
    expect(Question::withTrashed()->find($answeredQuestion->id))->not->toBeNull();
    expect($answer->refresh()->question_id)->toBe($answeredQuestion->id);
    expect($answer->selected_option_id)->toBe($option->id);
});

test('editing a question preserves the historical answer that referenced its old option', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $question = Question::factory()->for($quiz)->create();
    $oldCorrectOption = QuestionOption::factory()->for($question)->correct()->create(['option_text' => 'Opsi Lama']);
    QuestionOption::factory()->for($question)->create();

    $attempt = QuizAttempt::factory()->for($quiz)->completed(100)->create();
    $answer = QuizAnswer::factory()
        ->for($attempt, 'attempt')
        ->for($question, 'question')
        ->create(['selected_option_id' => $oldCorrectOption->id, 'is_correct' => true]);

    $this->actingAs($admin);
    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])
        ->call('editQuestion', $question->id)
        ->set('options', ['Opsi Baru 1', 'Opsi Baru 2'])
        ->set('correctIndex', 0)
        ->call('save')
        ->assertHasNoErrors();

    // Opsi baru kebentuk, opsi lama ilang dari query normal...
    expect($question->options()->count())->toBe(2);
    expect(QuestionOption::find($oldCorrectOption->id))->toBeNull();
    // ...tapi jawaban lama yang nunjuk ke opsi lama itu tetap nggak berubah.
    expect($answer->refresh()->selected_option_id)->toBe($oldCorrectOption->id);
    expect(QuestionOption::withTrashed()->find($oldCorrectOption->id))->not->toBeNull();
});

test('admin can reorder questions', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $first = Question::factory()->for($quiz)->create(['order' => 0]);
    $second = Question::factory()->for($quiz)->create(['order' => 1]);
    $this->actingAs($admin);

    Volt::test('admin.quizzes.questions', ['quiz' => $quiz])->call('moveDown', $first->id);

    expect($first->refresh()->order)->toBe(1);
    expect($second->refresh()->order)->toBe(0);
});

test('a new question gets a unique order even after a middle question was deleted', function () {
    $quiz = Quiz::factory()->create();
    $service = app(QuestionService::class);
    $payload = fn (string $text) => ['question_text' => $text, 'points' => 1, 'options' => ['A', 'B'], 'correct_index' => 0];

    $first = $service->create($quiz, $payload('Satu'));
    $second = $service->create($quiz, $payload('Dua'));
    $third = $service->create($quiz, $payload('Tiga'));

    $service->delete($second);
    $fourth = $service->create($quiz, $payload('Empat'));

    $orders = $quiz->questions()->pluck('order');

    expect($orders)->toHaveCount(3);
    expect($orders->unique())->toHaveCount(3);
    expect($fourth->order)->toBeGreaterThan($third->order);
});
