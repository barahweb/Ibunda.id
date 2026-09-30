<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('admin sees quiz management stats on the dashboard', function () {
    $admin = User::factory()->admin()->create();
    Quiz::factory()->published()->create();
    Quiz::factory()->draft()->create();

    $this->actingAs($admin);

    Volt::test('dashboard')
        ->assertSee('Total Quiz')
        ->assertSee('Kelola Quiz')
        ->assertDontSee('Kerjakan Quiz');
});

test('admin sees analytics charts when submission data exists', function () {
    $admin = User::factory()->admin()->create();
    $quizA = Quiz::factory()->published()->create(['title' => 'Quiz A', 'passing_score' => 70]);
    $quizB = Quiz::factory()->published()->create(['title' => 'Quiz B', 'passing_score' => 70]);
    $participant = User::factory()->create();

    QuizAttempt::factory()->for($quizA)->for($participant)->completed(90)->create();
    QuizAttempt::factory()->for($quizA)->for($participant)->completed(50)->create();
    QuizAttempt::factory()->for($quizB)->for($participant)->completed(80)->create();

    $this->actingAs($admin);

    Volt::test('dashboard')
        ->assertOk()
        ->assertSee('Analitik')
        ->assertSee('Tren Submission')
        ->assertSee('Quiz Terpopuler')
        ->assertSee('Tingkat Kelulusan')
        ->assertSee('Quiz A')
        ->assertSee('dari 3 attempt selesai')
        ->assertDontSee('Belum ada data submission');
});

test('admin sees empty analytics state when there is no submission data', function () {
    $admin = User::factory()->admin()->create();
    Quiz::factory()->published()->create();

    $this->actingAs($admin);

    Volt::test('dashboard')
        ->assertOk()
        ->assertSee('Belum ada data submission buat ditampilkan grafiknya.');
});

test('participant sees their own quiz stats on the dashboard', function () {
    $participant = User::factory()->create();
    Quiz::factory()->published()->create();
    QuizAttempt::factory()->for($participant)->completed(80)->create();

    $this->actingAs($participant);

    Volt::test('dashboard')
        ->assertSee('Quiz Tersedia')
        ->assertSee('Kerjakan Quiz')
        ->assertDontSee('Kelola Quiz');
});
