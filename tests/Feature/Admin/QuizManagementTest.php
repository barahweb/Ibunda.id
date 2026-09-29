<?php

use App\Models\Quiz;
use App\Models\User;
use Livewire\Volt\Volt;

test('guest is redirected to login', function () {
    $response = $this->get(route('admin.quizzes.index'));

    $response->assertRedirect(route('login'));
});

test('participant cannot access quiz management', function () {
    $participant = User::factory()->create();

    $response = $this->actingAs($participant)->get(route('admin.quizzes.index'));

    $response->assertForbidden();
});

test('admin can view the quiz list', function () {
    $admin = User::factory()->admin()->create();
    Quiz::factory()->for($admin, 'creator')->create(['title' => 'Tes Wawasan Umum']);

    $response = $this->actingAs($admin)->get(route('admin.quizzes.index'));

    $response->assertOk()->assertSee('Tes Wawasan Umum');
});

test('admin can create a quiz as draft', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.quizzes.index')
        ->call('createQuiz')
        ->set('title', 'Tes Logika')
        ->set('description', 'Soal-soal logika dasar')
        ->set('time_limit_minutes', 30)
        ->set('passing_score', 70)
        ->call('save')
        ->assertHasNoErrors();

    $quiz = Quiz::where('title', 'Tes Logika')->first();

    expect($quiz)->not->toBeNull();
    expect($quiz->status)->toBe(Quiz::STATUS_DRAFT);
    expect($quiz->created_by)->toBe($admin->id);
    expect($quiz->time_limit_minutes)->toBe(30);
    expect($quiz->passing_score)->toBe(70);
});

test('title is required to save a quiz', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.quizzes.index')
        ->call('createQuiz')
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

test('admin can edit an existing quiz', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quiz = Quiz::factory()->for($admin, 'creator')->create(['title' => 'Judul Lama']);

    Volt::test('admin.quizzes.index')
        ->call('editQuiz', $quiz->id)
        ->set('title', 'Judul Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($quiz->refresh()->title)->toBe('Judul Baru');
});

test('admin can toggle publish status', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quiz = Quiz::factory()->for($admin, 'creator')->draft()->create();

    Volt::test('admin.quizzes.index')->call('togglePublish', $quiz->id);
    expect($quiz->refresh()->status)->toBe(Quiz::STATUS_PUBLISHED);

    Volt::test('admin.quizzes.index')->call('togglePublish', $quiz->id);
    expect($quiz->refresh()->status)->toBe(Quiz::STATUS_DRAFT);
});

test('admin can delete a quiz', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quiz = Quiz::factory()->for($admin, 'creator')->create();

    Volt::test('admin.quizzes.index')->call('deleteQuiz', $quiz->id);

    expect(Quiz::find($quiz->id))->toBeNull();
});

test('search filters quizzes by title', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Quiz::factory()->for($admin, 'creator')->create(['title' => 'Tes Matematika']);
    Quiz::factory()->for($admin, 'creator')->create(['title' => 'Tes Bahasa']);

    Volt::test('admin.quizzes.index')
        ->set('search', 'Matematika')
        ->assertSee('Tes Matematika')
        ->assertDontSee('Tes Bahasa');
});
