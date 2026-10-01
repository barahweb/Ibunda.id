<?php

use App\Models\AssessmentAttempt;
use App\Models\User;
use App\Services\AssessmentInterpretationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

function interpretableAttempt(?User $user = null): AssessmentAttempt
{
    $user ??= User::factory()->create(['name' => 'Rahasia Banget', 'email' => 'rahasia@example.com']);

    return AssessmentAttempt::factory()->for($user)->completed('INTJ', ['EI' => 30, 'SN' => 30, 'TF' => 10, 'JP' => 10])->create();
}

/**
 * @return array{0: string, 1: string} pola URL buat Http::fake dan header key
 */
function useAiProvider(string $provider): array
{
    config(['services.ai.provider' => $provider, "services.{$provider}.key" => 'test-key']);

    return $provider === 'gemini' ? ['generativelanguage.googleapis.com/*', 'x-goog-api-key'] : ['api.anthropic.com/*', 'x-api-key'];
}

function aiResponseBody(string $provider, string $text): array
{
    return $provider === 'gemini'
        ? ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]]
        : ['content' => [['type' => 'text', 'text' => $text]]];
}

dataset('ai providers', ['gemini', 'anthropic']);

test('generating stores the interpretation and sends only type and scores', function (string $provider) {
    [$url, $keyHeader] = useAiProvider($provider);
    Http::fake([$url => Http::response(aiResponseBody($provider, 'Kamu orang yang terencana.'))]);
    $attempt = interpretableAttempt();

    $text = app(AssessmentInterpretationService::class)->generate($attempt);

    expect($text)->toBe('Kamu orang yang terencana.');
    expect($attempt->fresh()->ai_interpretation)->toBe('Kamu orang yang terencana.');
    expect($attempt->fresh()->ai_interpreted_at)->not->toBeNull();

    Http::assertSent(function ($request) use ($keyHeader) {
        $body = json_encode($request->data());

        return $request->hasHeader($keyHeader, 'test-key')
            && !str_contains($request->url(), 'test-key')
            && str_contains($body, 'INTJ')
            && !str_contains($body, 'Rahasia Banget')
            && !str_contains($body, 'rahasia@example.com');
    });
})->with('ai providers');

test('an existing interpretation is returned without calling the API again', function (string $provider) {
    [$url] = useAiProvider($provider);
    Http::fake([$url => Http::response(aiResponseBody($provider, 'Sekali saja.'))]);
    $attempt = interpretableAttempt();
    $service = app(AssessmentInterpretationService::class);

    $service->generate($attempt);
    $service->generate($attempt->fresh());

    Http::assertSentCount(1);
})->with('ai providers');

test('it refuses when the key of the chosen provider is not configured', function () {
    config(['services.ai.provider' => 'gemini', 'services.gemini.key' => null, 'services.anthropic.key' => 'cuma-anthropic']);
    Http::fake();

    expect(fn () => app(AssessmentInterpretationService::class)->generate(interpretableAttempt()))
        ->toThrow(DomainException::class, 'belum diaktifkan');

    Http::assertNothingSent();
});

test('an unknown provider name falls back to gemini', function () {
    config(['services.ai.provider' => 'asal-ketik', 'services.gemini.key' => 'test-key']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(aiResponseBody('gemini', 'Jalan lewat gemini.'))]);

    expect(app(AssessmentInterpretationService::class)->generate(interpretableAttempt()))->toBe('Jalan lewat gemini.');
});

test('a failed API call stores nothing and can be retried', function (string $provider) {
    [$url] = useAiProvider($provider);
    Sleep::fake();
    Http::fake([$url => Http::sequence()
        ->push(['error' => ['message' => 'overloaded']], 503)
        ->push(['error' => ['message' => 'overloaded']], 503)
        ->push(aiResponseBody($provider, 'Berhasil di percobaan berikutnya.'))]);
    $attempt = interpretableAttempt();

    expect(fn () => app(AssessmentInterpretationService::class)->generate($attempt))->toThrow(DomainException::class);
    expect($attempt->fresh()->ai_interpretation)->toBeNull();
    expect(app(AssessmentInterpretationService::class)->generate($attempt->fresh()))->toBe('Berhasil di percobaan berikutnya.');
})->with('ai providers');

test('one temporary overload is retried automatically', function (string $provider) {
    [$url] = useAiProvider($provider);
    Sleep::fake();
    Http::fake([$url => Http::sequence()
        ->push(['error' => ['message' => 'overloaded']], 503)
        ->push(aiResponseBody($provider, 'Lolos setelah retry.'))]);

    expect(app(AssessmentInterpretationService::class)->generate(interpretableAttempt()))->toBe('Lolos setelah retry.');
    Http::assertSentCount(2);
})->with('ai providers');

test('gemini thought parts are not included in the interpretation', function () {
    [$url] = useAiProvider('gemini');
    Http::fake([$url => Http::response(['candidates' => [['content' => ['parts' => [
        ['text' => 'pikiran internal model', 'thought' => true],
        ['text' => 'Paragraf satu. '],
        ['text' => 'Paragraf dua.'],
    ]]]]])]);

    expect(app(AssessmentInterpretationService::class)->generate(interpretableAttempt()))->toBe('Paragraf satu. Paragraf dua.');
});

test('an empty answer from the API is treated as a failure', function (string $provider) {
    [$url] = useAiProvider($provider);
    Http::fake([$url => Http::response(aiResponseBody($provider, '   '))]);
    $attempt = interpretableAttempt();

    expect(fn () => app(AssessmentInterpretationService::class)->generate($attempt))->toThrow(DomainException::class);
    expect($attempt->fresh()->ai_interpretation)->toBeNull();
})->with('ai providers');

test('a failed API call logs the status and the error message without the key', function () {
    [$url] = useAiProvider('gemini');
    config(['services.gemini.key' => 'rahasia-key-123']);
    Sleep::fake();
    Http::fake([$url => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);
    Log::spy();

    expect(fn () => app(AssessmentInterpretationService::class)->generate(interpretableAttempt()))->toThrow(DomainException::class);

    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context['status'] === 429
        && $context['error'] === 'quota exceeded'
        && !str_contains(json_encode($context), 'rahasia-key-123'))->once();
});

test('a permanent error such as a 400 is not retried', function (string $provider) {
    [$url] = useAiProvider($provider);
    Sleep::fake();
    Http::fake([$url => Http::response(['error' => ['message' => 'credit balance is too low']], 400)]);

    expect(fn () => app(AssessmentInterpretationService::class)->generate(interpretableAttempt()))->toThrow(DomainException::class);
    Http::assertSentCount(1);
})->with('ai providers');

test('the prompt labels strength by distance from the midpoint, not by the raw score', function () {
    [$url] = useAiProvider('gemini');
    Http::fake([$url => Http::response(aiResponseBody('gemini', 'Teks.'))]);
    $attempt = AssessmentAttempt::factory()->completed('INTJ', ['EI' => 30, 'SN' => 25, 'TF' => 12, 'JP' => 20])->create();

    app(AssessmentInterpretationService::class)->generate($attempt);

    Http::assertSent(function ($request) {
        $prompt = $request->data()['contents'][0]['parts'][0]['text'];

        return str_contains($prompt, 'EI: condong ke I (Introversion')
            && str_contains($prompt, ', kekuatan sedang')
            && str_contains($prompt, 'SN: condong ke N (Intuition')
            && str_contains($prompt, ', kekuatan tipis')
            && str_contains($prompt, 'TF: condong ke T (Thinking')
            && str_contains($prompt, ', kekuatan kuat')
            && str_contains($prompt, 'JP: condong ke J (Judging, suka terstruktur')
            && !str_contains($prompt, 'dari 40');
    });
});
