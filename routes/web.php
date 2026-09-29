<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Volt::route('quizzes', 'quizzes.index')->name('quizzes.index');
    Volt::route('quizzes/history', 'quizzes.history')->name('quizzes.history');
    Volt::route('quizzes/{quiz}/attempt', 'quizzes.attempt')->name('quizzes.attempt');
    Volt::route('quizzes/attempts/{attempt}/result', 'quizzes.result')->name('quizzes.attempts.result');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('quizzes', 'admin.quizzes.index')->name('quizzes.index');
    Volt::route('quizzes/{quiz}/questions', 'admin.quizzes.questions')->name('quizzes.questions');
    Volt::route('quizzes/{quiz}/submissions', 'admin.quizzes.submissions')->name('quizzes.submissions');
});

require __DIR__.'/auth.php';
