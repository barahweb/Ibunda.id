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

        if (!$admin) {
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

        $this->seedQuiz($admin, 'Geografi Indonesia', 'Gunung, danau, sungai, dan kota di Nusantara.', 70, 15, [
            ['Gunung tertinggi di Indonesia adalah?', ['Gunung Kerinci', 'Gunung Semeru', 'Puncak Jaya', 'Gunung Rinjani'], 2],
            ['Danau terbesar di Indonesia berdasarkan luasnya adalah?', ['Danau Singkarak', 'Danau Toba', 'Danau Poso', 'Danau Maninjau'], 1],
            ['Selat yang memisahkan Pulau Jawa dan Pulau Sumatra adalah?', ['Selat Sunda', 'Selat Bali', 'Selat Makassar', 'Selat Malaka'], 0],
            ['Ibu kota Provinsi Jawa Timur adalah?', ['Malang', 'Surabaya', 'Semarang', 'Kediri'], 1],
            ['Sungai terpanjang di Indonesia adalah?', ['Sungai Mahakam', 'Sungai Musi', 'Sungai Barito', 'Sungai Kapuas'], 3],
            ['Kota yang dilalui garis khatulistiwa adalah?', ['Banjarmasin', 'Samarinda', 'Pontianak', 'Balikpapan'], 2],
            ['Berapa jumlah zona waktu di Indonesia?', ['2', '3', '4', '5'], 1],
            ['Letusan Gunung Tambora tahun 1815 terjadi di pulau?', ['Lombok', 'Sumbawa', 'Flores', 'Bali'], 1],
            ['Pulau Dewata adalah sebutan untuk pulau?', ['Lombok', 'Madura', 'Nias', 'Bali'], 3],
            ['Provinsi dengan jumlah penduduk terbanyak di Indonesia adalah?', ['Jawa Barat', 'Jawa Timur', 'Jawa Tengah', 'Sumatera Utara'], 0],
        ]);

        $this->seedQuiz($admin, 'Bahasa Indonesia', 'Kata baku, majas, dan ejaan.', 70, 12, [
            ['Penulisan kata baku yang benar adalah?', ['Apotik', 'Apotek', 'Apotheek', 'Apotex'], 1],
            ['Bentuk baku dari kata "analisa" adalah?', ['Analisa', 'Analisys', 'Analisis', 'Anelisis'], 2],
            ['Antonim dari kata "rajin" adalah?', ['Malas', 'Giat', 'Tekun', 'Ulet'], 0],
            ['Sinonim dari kata "cantik" adalah?', ['Buruk', 'Elok', 'Jelek', 'Kasar'], 1],
            ['Kata dasar dari "memperbaiki" adalah?', ['Perbaiki', 'Baik', 'Bai', 'Memperbaik'], 1],
            ['Majas yang melebih-lebihkan sesuatu disebut?', ['Litotes', 'Personifikasi', 'Hiperbola', 'Eufemisme'], 2],
            ['Kalimat "Angin berbisik di telingaku" menggunakan majas?', ['Personifikasi', 'Hiperbola', 'Metafora', 'Sinekdoke'], 0],
            ['Penulisan kata depan "di" yang benar adalah?', ['dirumah', 'di rumah', 'di-rumah', 'dirumah-'], 1],
            ['Berapa jumlah huruf vokal dalam abjad bahasa Indonesia?', ['3', '4', '6', '5'], 3],
            ['Bahasa Indonesia diikrarkan sebagai bahasa persatuan pada peristiwa?', ['Kebangkitan Nasional 1908', 'Proklamasi 1945', 'Konferensi Meja Bundar', 'Sumpah Pemuda 1928'], 3],
        ]);

        $this->seedQuiz($admin, 'IPA Dasar', 'Sains dasar tentang alam, tubuh, dan materi.', 70, 10, [
            ['Planet yang paling dekat dengan Matahari adalah?', ['Venus', 'Merkurius', 'Mars', 'Bumi'], 1],
            ['Rumus kimia air adalah?', ['H2O', 'HO2', 'H2O2', 'OH'], 0],
            ['Gas yang diserap tumbuhan untuk fotosintesis adalah?', ['Oksigen', 'Nitrogen', 'Karbon dioksida', 'Hidrogen'], 2],
            ['Organ yang memompa darah ke seluruh tubuh adalah?', ['Paru-paru', 'Ginjal', 'Hati', 'Jantung'], 3],
            ['Satuan gaya dalam Sistem Internasional adalah?', ['Joule', 'Watt', 'Newton', 'Pascal'], 2],
            ['Air mendidih pada suhu berapa derajat Celsius (tekanan normal)?', ['90', '100', '110', '120'], 1],
            ['Perubahan wujud dari cair menjadi gas disebut?', ['Membeku', 'Mengembun', 'Menguap', 'Menyublim'], 2],
            ['Zat hijau pada daun disebut?', ['Klorofil', 'Hemoglobin', 'Melanin', 'Keratin'], 0],
            ['Berapa jumlah tulang manusia dewasa?', ['106', '206', '306', '260'], 1],
            ['Planet yang dijuluki planet merah adalah?', ['Mars', 'Jupiter', 'Venus', 'Saturnus'], 0],
        ]);

        $this->seedQuiz($admin, 'Teknologi & Internet', 'Dasar-dasar komputer dan internet.', 70, 10, [
            ['Kepanjangan dari HTML adalah?', ['HyperTool Markup Language', 'High Text Machine Language', 'HyperText Markup Language', 'Home Tool Markup Language'], 2],
            ['Kepanjangan dari WWW adalah?', ['World Wide Web', 'Web World Wide', 'Wide World Web', 'World Web Wide'], 0],
            ['Protokol yang mengamankan komunikasi web lewat enkripsi adalah?', ['FTP', 'HTTP', 'SMTP', 'HTTPS'], 3],
            ['Fungsi utama DNS adalah?', ['Mengamankan data', 'Menerjemahkan nama domain menjadi alamat IP', 'Mempercepat internet', 'Menyimpan email'], 1],
            ['Manakah yang termasuk sistem operasi?', ['Photoshop', 'Linux', 'Excel', 'Chrome'], 1],
            ['Satu byte terdiri dari berapa bit?', ['4', '16', '8', '1024'], 2],
            ['Kepanjangan dari CPU adalah?', ['Central Processing Unit', 'Computer Power Unit', 'Central Program Utility', 'Core Process Unit'], 0],
            ['Port bawaan untuk HTTPS adalah?', ['21', '80', '443', '3306'], 2],
            ['Phishing adalah?', ['Penipuan dengan situs atau pesan tiruan untuk mencuri data', 'Cara mempercepat jaringan', 'Jenis sistem operasi', 'Alat membersihkan virus'], 0],
            ['Ekstensi file yang umum dipakai untuk gambar adalah?', ['.php', '.png', '.sql', '.exe'], 1],
        ]);

        $this->seedQuiz($admin, 'Pemrograman Dasar', 'Konsep dasar PHP, Laravel, dan SQL.', 70, 15, [
            ['Laravel adalah framework untuk bahasa pemrograman?', ['Python', 'Java', 'PHP', 'Ruby'], 2],
            ['Perintah artisan untuk menjalankan migration adalah?', ['php artisan migrate', 'php artisan make:model', 'php artisan serve', 'php artisan route:list'], 0],
            ['Operator perbandingan yang memeriksa nilai dan tipe sekaligus di PHP adalah?', ['==', '=', '===', '!='], 2],
            ['Fungsi PHP untuk menghitung jumlah elemen array adalah?', ['size()', 'length()', 'count()', 'total()'], 2],
            ['Variabel di PHP diawali dengan tanda?', ['@', '$', '#', '&'], 1],
            ['Tipe data yang hanya bernilai true atau false adalah?', ['string', 'integer', 'array', 'boolean'], 3],
            ['Composer adalah?', ['Pengelola dependency untuk PHP', 'Editor kode', 'Server database', 'Framework CSS'], 0],
            ['Manakah yang bukan struktur perulangan di PHP?', ['for', 'while', 'foreach', 'if'], 3],
            ['Method HTTP yang umum dipakai untuk mengambil data adalah?', ['POST', 'GET', 'PUT', 'DELETE'], 1],
            ['Perintah SQL untuk mengambil data dari tabel adalah?', ['INSERT', 'UPDATE', 'SELECT', 'DROP'], 2],
        ]);

        $this->seedQuiz($admin, 'Bahasa Inggris Dasar', 'Grammar dan kosakata tingkat dasar.', 70, 10, [
            ['She ___ to school every day.', ['go', 'goes', 'going', 'gone'], 1],
            ['Bentuk lampau (past tense) dari "eat" adalah?', ['eated', 'eaten', 'eats', 'ate'], 3],
            ['Bentuk jamak dari "child" adalah?', ['childs', 'childes', 'children', 'childrens'], 2],
            ['I have ___ apple.', ['a', 'an', 'the', 'two'], 1],
            ['Lawan kata dari "big" adalah?', ['small', 'tall', 'wide', 'heavy'], 0],
            ['They ___ playing football now.', ['is', 'am', 'are', 'be'], 2],
            ['Arti kata "library" adalah?', ['Toko buku', 'Perpustakaan', 'Sekolah', 'Museum'], 1],
            ['He ___ not like coffee.', ['do', 'does', 'is', 'are'], 1],
            ['Yesterday I ___ a movie.', ['watch', 'watching', 'watches', 'watched'], 3],
            ['Kata "beautiful" termasuk jenis kata?', ['Noun', 'Verb', 'Adjective', 'Adverb'], 2],
        ]);

        $this->seedQuiz($admin, 'Budaya Nusantara', 'Rumah adat, tarian, dan tradisi daerah.', 70, null, [
            ['Rumah adat suku Minangkabau disebut?', ['Rumah Gadang', 'Joglo', 'Tongkonan', 'Honai'], 0],
            ['Tari Saman berasal dari daerah?', ['Bali', 'Aceh', 'Jawa Barat', 'Papua'], 1],
            ['Alat musik tradisional dari bambu yang berasal dari Jawa Barat adalah?', ['Sasando', 'Gamelan', 'Tifa', 'Angklung'], 3],
            ['Batik diakui UNESCO sebagai warisan budaya tak benda pada tahun?', ['2005', '2009', '2012', '2015'], 1],
            ['Rumah adat suku Toraja disebut?', ['Rumah Gadang', 'Tongkonan', 'Joglo', 'Honai'], 1],
            ['Rendang berasal dari daerah?', ['Minangkabau', 'Jawa Tengah', 'Bali', 'Makassar'], 0],
            ['Wayang kulit berasal dari pulau?', ['Sumatra', 'Kalimantan', 'Jawa', 'Sulawesi'], 2],
            ['Upacara Ngaben dilakukan oleh masyarakat?', ['Bali', 'Aceh', 'Toraja', 'Betawi'], 0],
            ['Kain tradisional Ulos berasal dari suku?', ['Dayak', 'Batak', 'Sunda', 'Bugis'], 1],
            ['Kesenian Reog berasal dari Ponorogo di provinsi?', ['Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Yogyakarta'], 2],
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: array<int, string>, 2: int}>  $questions
     */
    private function seedQuiz(User $creator, string $title, string $description, int $passingScore, ?int $timeLimitMinutes, array $questions): void
    {
        // created_by sengaja gak masuk $fillable (gak boleh diisi dari input user), jadi diisi
        // langsung, sama kayak QuizService::create().
        $quiz = new Quiz([
            'title' => $title,
            'description' => $description,
            'status' => Quiz::STATUS_PUBLISHED,
            'passing_score' => $passingScore,
            'time_limit_minutes' => $timeLimitMinutes,
        ]);
        $quiz->created_by = $creator->id;
        $quiz->save();

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
