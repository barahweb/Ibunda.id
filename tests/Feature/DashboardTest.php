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
