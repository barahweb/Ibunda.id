<?php

namespace Database\Seeders;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    /**
     * Seed a handful of published quizzes with real questions, for local testing.
     */
    public function run(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        if (! $admin) {
            return;
        }

        $this->seedQuiz($admin, 'Pengetahuan Umum', 'Soal-soal wawasan umum sehari-hari.', 70, 15, [
            ['Apa ibu kota Indonesia?', ['Jakarta', 'Bandung', 'Surabaya', 'Medan'], 0],
            ['Planet terbesar di tata surya adalah?', ['Jupiter', 'Mars', 'Venus', 'Saturnus'], 0],
            ['Berapa jumlah provinsi di Indonesia saat ini?', ['38', '34', '36', '33'], 0],
            ['Apa bahasa resmi negara Indonesia?', ['Bahasa Indonesia', 'Bahasa Melayu', 'Bahasa Jawa', 'Bahasa Sunda'], 0],
            ['Hewan apa yang menjadi lambang negara Indonesia?', ['Garuda', 'Elang', 'Harimau', 'Komodo'], 0],
            ['Samudra terluas di dunia adalah?', ['Pasifik', 'Atlantik', 'Hindia', 'Arktik'], 0],
            ['Berapa jumlah sila dalam Pancasila?', ['5', '4', '6', '3'], 0],
            ['Apa mata uang resmi Indonesia?', ['Rupiah', 'Ringgit', 'Baht', 'Peso'], 0],
        ]);

        $this->seedQuiz($admin, 'Matematika Dasar', 'Latihan berhitung dasar.', 70, 10, [
            ['7 + 8 = ?', ['15', '14', '16', '13'], 0],
            ['12 × 4 = ?', ['48', '44', '52', '36'], 0],
            ['100 ÷ 5 = ?', ['20', '25', '15', '10'], 0],
            ['Akar kuadrat dari 64 adalah?', ['8', '6', '9', '7'], 0],
            ['15 − 9 = ?', ['6', '5', '7', '4'], 0],
            ['3 pangkat 2 (3²) = ?', ['9', '6', '3', '12'], 0],
        ]);

        $this->seedQuiz($admin, 'Sejarah Indonesia', 'Sejarah kemerdekaan dan tokoh bangsa.', 70, null, [
            ['Tahun berapa Indonesia merdeka?', ['1945', '1942', '1949', '1950'], 0],
            ['Siapa presiden pertama Indonesia?', ['Soekarno', 'Soeharto', 'Habibie', 'Mohammad Hatta'], 0],
            ['Organisasi pergerakan nasional pertama di Indonesia adalah?', ['Budi Utomo', 'Sarekat Islam', 'Indische Partij', 'PNI'], 0],
            ['Peristiwa penculikan Soekarno-Hatta sebelum proklamasi disebut peristiwa?', ['Rengasdengklok', 'Bandung Lautan Api', 'Serangan Umum 1 Maret', 'Madiun'], 0],
            ['Hari Sumpah Pemuda diperingati setiap tanggal?', ['28 Oktober', '17 Agustus', '20 Mei', '10 November'], 0],
            ['Siapa wakil presiden pertama Indonesia?', ['Mohammad Hatta', 'Sutan Sjahrir', 'Ahmad Soebardjo', 'Sukarni'], 0],
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: array<int, string>, 2: int}>  $questions
     */
    private function seedQuiz(User $creator, string $title, string $description, int $passingScore, ?int $timeLimitMinutes, array $questions): void
    {
        $quiz = Quiz::create([
            'created_by' => $creator->id,
            'title' => $title,
            'description' => $description,
            'status' => Quiz::STATUS_PUBLISHED,
            'passing_score' => $passingScore,
            'time_limit_minutes' => $timeLimitMinutes,
        ]);

        foreach ($questions as $order => [$text, $options, $correctIndex]) {
            $question = $quiz->questions()->create([
                'question_text' => $text,
                'points' => 1,
                'order' => $order,
            ]);

            foreach ($options as $index => $optionText) {
                $question->options()->create([
                    'option_text' => $optionText,
                    'is_correct' => $index === $correctIndex,
                    'order' => $index,
                ]);
            }
        }
    }
}
