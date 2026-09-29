<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
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
