<?php

namespace App\Services;

use App\Helper\OejtsScorer;
use App\Models\AssessmentAttempt;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssessmentInterpretationService
{
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';

    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Kamu menulis interpretasi singkat hasil tes kepribadian berbasis OEJTS (mirip MBTI) dalam bahasa Indonesia yang santai tapi sopan.
Aturan:
- Tulis 3 paragraf pendek: gambaran umum, kekuatan, lalu hal yang bisa dikembangkan.
- Langsung ke isi, tanpa sapaan pembuka atau ucapan selamat.
- Sebut istilah dimensi persis seperti yang diberikan (misal Judging, Perceiving). Jangan menerjemahkan atau mengartikannya sendiri.
- Pakai label kekuatan per dimensi yang diberikan (kuat, sedang, tipis). Jangan menafsirkan angka skor mentah sendiri.
- Ini tes populer untuk refleksi diri, bukan diagnosis psikologis. Akhiri dengan satu kalimat pengingat soal itu.
- Jangan pakai markdown, judul, atau bullet. Teks polos saja, maksimal sekitar 220 kata.
- Jangan menebak identitas, pekerjaan, atau kondisi kesehatan orangnya.
PROMPT;

    public function isConfigured(): bool
    {
        return filled(config("services.{$this->provider()}.key"));
    }

    private function provider(): string
    {
        return config('services.ai.provider') === 'anthropic' ? 'anthropic' : 'gemini';
    }

    /**
     * Bikin interpretasi sekali per attempt. Kalau sudah ada, balikin yang tersimpan tanpa
     * manggil API lagi, jadi klik ganda atau dua tab gak menggandakan biaya.
     *
     * @throws DomainException kalau API belum dikonfigurasi, lagi diproses, atau gagal
     */
    public function generate(AssessmentAttempt $attempt): string
    {
        if (filled($attempt->ai_interpretation)) {
            return $attempt->ai_interpretation;
        }

        if (!$this->isConfigured()) {
            throw new DomainException('Fitur interpretasi AI belum diaktifkan.');
        }

        $lock = Cache::lock('assessment-interpretation:'.$attempt->id, 60);

        if (!$lock->get()) {
            throw new DomainException('Interpretasimu masih diproses, tunggu sebentar.');
        }

        try {
            $attempt->refresh();

            if (filled($attempt->ai_interpretation)) {
                return $attempt->ai_interpretation;
            }

            $text = $this->requestInterpretation($attempt);

            $attempt->forceFill(['ai_interpretation' => $text, 'ai_interpreted_at' => now()])->save();

            return $text;
        } finally {
            $lock->release();
        }
    }

    private function requestInterpretation(AssessmentAttempt $attempt): string
    {
        $prompt = $this->buildPrompt($attempt);

        try {
            $response = $this->provider() === 'anthropic'
                ? $this->sendToAnthropic($prompt)
                : $this->sendToGemini($prompt);
        } catch (Throwable $e) {
            Log::warning('AI request failed', ['provider' => $this->provider(), 'exception' => $e->getMessage()]);

            throw new DomainException('Gagal menghubungi layanan AI, coba lagi nanti.');
        }

        $text = $this->extractText($response);

        if ($response->failed() || $text === '') {
            Log::warning('AI returned no interpretation', [
                'provider' => $this->provider(),
                'status' => $response->status(),
                'error' => data_get($response->json(), 'error.message'),
            ]);

            throw new DomainException('Layanan AI belum bisa memberi interpretasi, coba lagi nanti.');
        }

        return $text;
    }

    /**
     * Gemini bisa ngirim beberapa part (termasuk part "thought"), jadi ambil semua teks
     * jawaban dan buang yang ditandai thought.
     */
    private function extractText(Response $response): string
    {
        if ($this->provider() === 'anthropic') {
            return trim((string) data_get($response->json(), 'content.0.text', ''));
        }

        return trim(collect(data_get($response->json(), 'candidates.0.content.parts', []))
            ->reject(fn ($part) => !empty($part['thought']))
            ->pluck('text')
            ->filter()
            ->implode(''));
    }

    /**
     * Ulang cuma buat error sementara (koneksi putus, 429, 5xx), bukan error permanen
     * kayak saldo habis atau key salah.
     */
    private function isTemporaryFailure(Throwable $exception): bool
    {
        if ($exception instanceof RequestException) {
            return in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
        }

        return true;
    }

    private function sendToGemini(string $prompt): Response
    {
        return Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->timeout((int) config('services.gemini.timeout'))
            ->retry(2, 1500, $this->isTemporaryFailure(...), throw: false)
            ->post(sprintf(self::GEMINI_URL, config('services.gemini.model')), [
                'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['maxOutputTokens' => 2000],
            ]);
    }

    private function sendToAnthropic(string $prompt): Response
    {
        return Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout((int) config('services.anthropic.timeout'))
            ->retry(2, 1500, $this->isTemporaryFailure(...), throw: false)
            ->post(self::ANTHROPIC_URL, [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 700,
                'system' => self::SYSTEM_PROMPT,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
    }

    private function letterMeaning(string $letter): string
    {
        return match ($letter) {
            'E' => 'Extraversion, mengambil energi dari interaksi dengan orang lain',
            'I' => 'Introversion, mengambil energi dari waktu sendiri dan refleksi',
            'S' => 'Sensing, fokus pada fakta nyata dan detail',
            'N' => 'Intuition, fokus pada pola, makna, dan kemungkinan',
            'T' => 'Thinking, memutuskan dengan logika dan objektivitas',
            'F' => 'Feeling, memutuskan dengan nilai dan perasaan orang',
            'J' => 'Judging, suka terstruktur, terencana, dan tuntas',
            default => 'Perceiving, suka fleksibel, spontan, dan terbuka pada pilihan',
        };
    }

    /**
     * Jarak skor dari batas tengah (24): makin jauh makin kuat condongnya, ke arah mana pun.
     */
    private function strengthLabel(int $sum): string
    {
        $distance = abs($sum - 24);

        return match (true) {
            $distance <= 3 => 'tipis (hampir seimbang)',
            $distance <= 8 => 'sedang',
            default => 'kuat',
        };
    }

    /**
     * Cuma kirim tipe dan skor, tanpa nama atau email peserta.
     */
    private function buildPrompt(AssessmentAttempt $attempt): string
    {
        $lines = collect(OejtsScorer::DIMENSIONS)->map(function (string $dimension) use ($attempt) {
            $sum = (int) ($attempt->dimension_scores[$dimension] ?? 0);
            $letter = OejtsScorer::letterForDimension($dimension, $sum);

            $strength = $this->strengthLabel($sum);

            return "- {$dimension}: condong ke {$letter} ({$this->letterMeaning($letter)}), kekuatan {$strength}";
        })->implode("\n");

        return "Tipe hasil: {$attempt->result_type}\nSkor per dimensi:\n{$lines}\n\nTulis interpretasinya.";
    }
}
