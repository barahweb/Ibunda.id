<?php

use App\Helper\OejtsScorer;
use App\Models\AssessmentQuestion;
use Illuminate\Support\Collection;

/**
 * 8 soal per dimensi (EI, SN, TF, JP), sama kayak struktur OEJTS asli — biar jumlahnya
 * jatuh di rentang 8-40 yang bener (nyebrang threshold 24 pas semua dijawab ekstrem).
 */
function scorerQuestions(): Collection
{
    return collect(OejtsScorer::DIMENSIONS)
        ->flatMap(fn ($dimension) => AssessmentQuestion::factory()->dimension($dimension)->count(8)->make());
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
