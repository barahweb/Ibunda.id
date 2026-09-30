<?php

namespace App\Exports;

use App\Models\QuizAttempt;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * @implements WithMapping<QuizAttempt>
 */
class QuizReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  Collection<int, QuizAttempt>  $attempts
     */
    public function __construct(private Collection $attempts)
    {
        //
    }

    public function collection(): Collection
    {
        return $this->attempts;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Peserta', 'Email', 'Quiz', 'Skor', 'Status', 'Tanggal Submit'];
    }

    /**
     * @return array<int, mixed>
     */
    public function map($attempt): array
    {
        return [
            $attempt->user->name,
            $attempt->user->email,
            $attempt->quiz->title,
            $attempt->score,
            $attempt->quiz->passing_score === null ? '-' : ($attempt->hasPassed() ? 'Lulus' : 'Belum Lulus'),
            $attempt->submitted_at->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
