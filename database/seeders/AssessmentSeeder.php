<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssessmentSeeder extends Seeder
{
    /**
     * Seed satu Tes Kepribadian (OEJTS) berisi 32 pernyataan terjemahan, untuk testing lokal.
     *
     * Konten: Open Extended Jungian Type Scales (OEJTS) 1.2 by Open Psychometrics, CC BY-NC-SA 4.0.
     */
    public function run(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        if (!$admin) {
            return;
        }

        $assessment = Assessment::create([
            'created_by' => $admin->id,
            'title' => 'Tes Kepribadian (OEJTS)',
            'description' => 'Pilih sisi yang paling menggambarkan dirimu di tiap pasang pernyataan. Tidak ada jawaban benar atau salah.',
            'status' => Assessment::STATUS_DRAFT,
        ]);

        foreach ($this->statements() as $order => [$dimension, $left, $right]) {
            $assessment->questions()->create([
                'dimension' => $dimension,
                'statement_left' => $left,
                'statement_right' => $right,
                'order' => $order,
            ]);
        }

        $assessment->update(['status' => Assessment::STATUS_PUBLISHED]);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function statements(): array
    {
        return [
            ['JP', 'Membuat daftar catatan', 'Mengandalkan ingatan'],
            ['TF', 'Ingin percaya', 'Skeptis'],
            ['EI', 'Bosan kalau sendirian', 'Butuh waktu sendiri'],
            ['SN', 'Menerima keadaan apa adanya', 'Tidak puas dengan keadaan sekarang'],
            ['JP', 'Menjaga kamar tetap rapi', 'Menaruh barang di mana saja'],
            ['TF', 'Dibilang "seperti robot" itu hinaan', 'Berusaha berpikir seperti mesin'],
            ['EI', 'Penuh energi', 'Santai dan kalem'],
            ['SN', 'Lebih suka ujian pilihan ganda', 'Lebih suka ujian esai'],
            ['JP', 'Terorganisir', 'Berantakan'],
            ['TF', 'Mudah tersinggung', 'Tahan banting'],
            ['EI', 'Paling baik kerja dalam kelompok', 'Paling baik kerja sendiri'],
            ['SN', 'Fokus pada masa lalu', 'Fokus pada masa depan'],
            ['JP', 'Merencanakan jauh-jauh hari', 'Merencanakan di menit terakhir'],
            ['TF', 'Ingin dicintai orang', 'Ingin dihormati orang'],
            ['EI', 'Semangat kalau ada pesta', 'Capek kalau ada pesta'],
            ['SN', 'Berbaur dengan orang lain', 'Tampil beda'],
            ['JP', 'Berkomitmen', 'Membiarkan pilihan tetap terbuka'],
            ['TF', 'Ingin jago membantu memperbaiki orang', 'Ingin jago memperbaiki barang'],
            ['EI', 'Lebih banyak bicara', 'Lebih banyak mendengarkan'],
            ['SN', 'Menceritakan apa yang terjadi', 'Menceritakan apa maknanya'],
            ['JP', 'Langsung menyelesaikan pekerjaan', 'Suka menunda'],
            ['TF', 'Mengikuti kata hati', 'Mengikuti logika'],
            ['EI', 'Suka keluar jalan-jalan', 'Suka diam di rumah'],
            ['SN', 'Ingin tahu detailnya', 'Ingin tahu gambaran besarnya'],
            ['JP', 'Mempersiapkan diri', 'Berimprovisasi'],
            ['TF', 'Moralitas didasari belas kasih', 'Moralitas didasari keadilan'],
            ['EI', 'Berteriak itu hal yang wajar', 'Sulit berteriak dengan keras'],
            ['SN', 'Empiris', 'Teoretis'],
            ['JP', 'Bekerja keras', 'Bermain sepuasnya'],
            ['TF', 'Menghargai emosi', 'Kurang nyaman dengan emosi'],
            ['EI', 'Suka tampil di depan orang', 'Menghindari bicara di depan umum'],
            ['SN', 'Ingin tahu "siapa/apa/kapan"', 'Ingin tahu "kenapa"'],
        ];
    }
}
