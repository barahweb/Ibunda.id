<?php

use App\Helper\OejtsScorer;
use App\Models\AssessmentQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * 8 soal per dimensi (EI, SN, TF, JP), sama kayak struktur OEJTS asli — biar jumlahnya
 * jatuh di rentang 8-40 yang bener (nyebrang threshold 24 pas semua dijawab ekstrem).
 */
function scorerQuestions(): Collection
{
    // make() gak ngisi id (HasUuids baru ngisi pas disimpan), jadi id dikasih manual.
    // Tanpa id unik, semua soal nabrak di 1 key array nilai dan test bisa lolos
    // walau pemetaan soal -> nilai -> dimensi salah.
    return collect(OejtsScorer::DIMENSIONS)
        ->flatMap(fn ($dimension) => AssessmentQuestion::factory()
            ->dimension($dimension)
            ->count(8)
            ->sequence(fn () => ['id' => (string) Str::uuid()])
            ->make());
}

test('all answers at 1 give the lowest possible sum for every dimension', function () {
    $questions = scorerQuestions();
    $values = $questions->mapWithKeys(fn ($question) => [$question->id => 1])->all();

    $sums = OejtsScorer::sumsByDimension($questions, $values);

    foreach (OejtsScorer::DIMENSIONS as $dimension) {
        expect($sums[$dimension])->toBe(8);
    }
});

test('all answers at 5 give the highest possible sum for every dimension', function () {
    $questions = scorerQuestions();
    $values = $questions->mapWithKeys(fn ($question) => [$question->id => 5])->all();

    $sums = OejtsScorer::sumsByDimension($questions, $values);

    foreach (OejtsScorer::DIMENSIONS as $dimension) {
        expect($sums[$dimension])->toBe(40);
    }
});

test('each answer is credited to its own question and dimension', function () {
    // Pola sengaja beda-beda per dimensi dan pas di sekitar threshold 24, biar salah
    // petakan soal -> nilai -> dimensi langsung kelihatan di angka akhirnya.
    $patterns = [
        'EI' => [5, 5, 5, 5, 1, 1, 1, 1], // 24 -> I (tepat di threshold)
        'SN' => [1, 1, 1, 1, 5, 5, 5, 4], // 23 -> S (mepet di bawah threshold)
        'TF' => [3, 3, 3, 3, 3, 3, 3, 3], // 24 -> F
        'JP' => [2, 2, 2, 2, 2, 2, 2, 2], // 16 -> J
    ];

    $questions = scorerQuestions();
    $values = [];

    foreach ($patterns as $dimension => $pattern) {
        $dimensionQuestions = $questions->where('dimension', $dimension)->values();

        foreach ($pattern as $index => $value) {
            $values[$dimensionQuestions[$index]->id] = $value;
        }
    }

    $sums = OejtsScorer::sumsByDimension($questions, $values);

    expect($sums)->toBe(['EI' => 24, 'SN' => 23, 'TF' => 24, 'JP' => 16]);
    expect(OejtsScorer::resolveResultType($sums))->toBe('ISFJ');
});

test('answers for a question that is not in the list are ignored', function () {
    $questions = scorerQuestions();
    $values = $questions->mapWithKeys(fn ($question) => [$question->id => 1])->all();
    $values[(string) Str::uuid()] = 5;

    $sums = OejtsScorer::sumsByDimension($questions, $values);

    expect($sums)->toBe(['EI' => 8, 'SN' => 8, 'TF' => 8, 'JP' => 8]);
});

test('unanswered questions do not contribute to their dimension sum', function () {
    $question = AssessmentQuestion::factory()->dimension('EI')->make();

    $sums = OejtsScorer::sumsByDimension(collect([$question]), []);

    expect($sums['EI'])->toBe(0);
});

test('a sum just below the threshold resolves to the dimension\'s first letter', function () {
    expect(OejtsScorer::letterForDimension('EI', 23))->toBe('E');
    expect(OejtsScorer::letterForDimension('JP', 23))->toBe('J');
});

test('a sum at or above the threshold resolves to the dimension\'s second letter', function () {
    expect(OejtsScorer::letterForDimension('EI', 24))->toBe('I');
    expect(OejtsScorer::letterForDimension('EI', 40))->toBe('I');
});

test('resolveResultType concatenates all four dimensions independently', function () {
    // EI 18 (<24 -> E), SN 30 (>=24 -> N), TF 22 (<24 -> T), JP 26 (>=24 -> P).
    $type = OejtsScorer::resolveResultType(['EI' => 18, 'SN' => 30, 'TF' => 22, 'JP' => 26]);

    expect($type)->toBe('ENTP');
});

test('answering everything at the extremes resolves to a full type', function () {
    $lowQuestions = scorerQuestions();
    $lowValues = $lowQuestions->mapWithKeys(fn ($question) => [$question->id => 1])->all();
    $lowType = OejtsScorer::resolveResultType(OejtsScorer::sumsByDimension($lowQuestions, $lowValues));

    $highQuestions = scorerQuestions();
    $highValues = $highQuestions->mapWithKeys(fn ($question) => [$question->id => 5])->all();
    $highType = OejtsScorer::resolveResultType(OejtsScorer::sumsByDimension($highQuestions, $highValues));

    expect($lowType)->toBe('ESTJ');
    expect($highType)->toBe('INFP');
});
