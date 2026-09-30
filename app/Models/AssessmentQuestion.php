<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentQuestion extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const DIMENSIONS = ['EI', 'SN', 'TF', 'JP'];

    protected $fillable = [
        'assessment_id',
        'dimension',
        'statement_left',
        'statement_right',
        'order',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function leftLetter(): string
    {
        return substr($this->dimension, 0, 1);
    }

    public function rightLetter(): string
    {
        return substr($this->dimension, 1, 1);
    }
}
